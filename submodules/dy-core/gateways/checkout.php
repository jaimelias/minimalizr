<?php

if (!defined('WPINC')) exit;

/** Registry and submission lifecycle shared by all checkout sources. */
final class Dy_Checkout
{
	private static array $sources = [];
	private static array $gateways = [];

	public static function register_source(string $id, Dy_Checkout_Source $adapter): void
	{
		self::$sources[sanitize_key($id)] = $adapter;
	}

	public static function register_gateway(Dy_Gateway $gateway): void
	{
		self::$gateways[$gateway->id] = $gateway;
	}

	public static function gateways(): array { return self::$gateways; }
	public static function gateway(string $id): ?Dy_Gateway { return self::$gateways[$id] ?? null; }

	public static function source_id(?array $tx = null): string
	{
		if ($tx !== null) return sanitize_key((string) dy_tx::service_value('source', '', $tx));
		return sanitize_key((string) secure_post('checkout_source', '', 'sanitize_key'))
			?: sanitize_key((string) apply_filters('dy_checkout_default_source', ''));
	}

	public static function source(string $id): ?Dy_Checkout_Source { return self::$sources[$id] ?? null; }

	public static function submit(): void
	{
		$source = self::source(self::source_id());
		if ($source !== null && $source->accepts_submission() && self::process($source)) {
			nocache_headers();
			wp_safe_redirect(trailingslashit(home_lang()) . 'dy-tx/' . rawurlencode((string) secure_post('tx_id')), 303);
			exit;
		}
	}

	/** Serialize payment and notification side effects, including concurrent POST retries. */
	public static function process(Dy_Checkout_Source $source): bool
	{
		if (secure_server('REQUEST_METHOD') !== 'POST' || !$source->accepts_submission()) return false;
		$tx_id = (string) secure_post('tx_id');
		if (!wp_is_uuid($tx_id, 4) || !dy_tx::validate($tx_id) || !$source->matches_submission(dy_tx::get_stored_tx($tx_id))) {
			dy_errors::add(__('Invalid tx_id.', 'dycore'));
			return false;
		}
		global $wpdb;
		$lock = 'dy_submit_' . substr(hash('sha256', $wpdb->prefix . $tx_id), 0, 54);
		if ((string) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 1)', $lock)) !== '1') {
			dy_errors::add(__('This request is already being processed. Please try again shortly.', 'dycore'), 409);
			return false;
		}
		try {
			if (!wp_using_ext_object_cache()) {
				wp_cache_delete('_transient_tx_id_' . $tx_id, 'options');
				wp_cache_delete('_transient_timeout_tx_id_' . $tx_id, 'options');
			}
			$tx = dy_tx::get_stored_tx($tx_id);
			if ($tx === null || !dy_tx::validate($tx_id)) return false;
			if ($tx['status'] === 'processing') {
				dy_errors::add(__('This request is still being processed. Please try again shortly.', 'dycore'), 409);
				return false;
			}
			if ($tx['status'] !== 'started') return true;
			if (!validate_turnstile((string) secure_post('cf-turnstile-response'), 'tx-submit')) return false;
			if (!$source->validate_submission()) return false;
			$tx = $source->prepare_transaction($tx);
			$intent = (string) dy_tx::service_value('intent', '', $tx);
			$gateway = self::gateway((string) dy_tx::service_value('gateway_id', '', $tx));
			if (($tx['tx_id'] ?? '') !== $tx_id || !in_array($intent, ['contact', 'estimate', 'payment'], true)
				|| ($intent === 'payment' && $gateway === null)) {
				dy_errors::add(__('The selected checkout action is unavailable.', 'dycore'));
				return false;
			}
			if ($intent === 'payment' && !$gateway->validate($tx)) return false;
			if (dy_errors::has_errors()) return false;
			$tx['status'] = 'processing';
			if (!self::store($tx)) return false;
			$tx['status'] = 'success';
			if ($intent === 'payment') $tx = $gateway->process_transaction($tx);
			$tx = $source->processed_transaction($tx);
			// Persist before notifications. A lost response must never permit a second charge.
			if (!self::store($tx)) return false;
			$stored = dy_tx::get_stored_tx($tx_id) ?? $tx;
			dy_tx::set_current_transaction($stored);
			try { $source->notify_transaction($stored); }
			finally { dy_tx::set_current_transaction(null); }
			return true;
		} finally {
			$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock));
		}
	}

	private static function store(array $tx): bool
	{
		if (dy_tx::update($tx)) return true;
		dy_errors::add(__('Unable to store the transaction result. Please contact us before submitting again.', 'dycore'), 503);
		return false;
	}

	public static function confirmation(array $result, array $tx): array
	{
		$id = (string) dy_tx::service_value('gateway_id', '', $tx);
		$gateway = self::gateway($id);
		if ($gateway !== null) $result = $gateway->confirmation_result($result, $tx);
		$source = self::source(self::source_id($tx));
		return $source !== null ? $source->confirmation_result($result, $tx) : $result;
	}
}
