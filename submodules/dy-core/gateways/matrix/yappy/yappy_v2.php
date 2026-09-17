<?php

if (!defined('WPINC')) exit;

require_once __DIR__ . '/yappy-v2-store.php';

/** Yappy's web component starts payments; only a verified IPN settles them. */
class yappy_v2 extends Dy_Gateway
{
	private const HOSTS = [
		'production' => ['api' => 'https://apipagosbg.bgeneral.cloud', 'sdk' => 'https://bt-cdn.yappy.cloud/v1/cdn/web-component-btn-yappy.js'],
		'sandbox' => ['api' => 'https://api-comecom-uat.yappycloud.com', 'sdk' => 'https://bt-cdn-uat.yappycloud.com/v1/cdn/web-component-btn-yappy.js'],
	];
	private string $environment = 'sandbox';
	private string $merchant = '';
	private string $domain = '';
	private string $signing_key = '';
	private bool $enabled = false;

	public function __construct()
	{
		$this->register('yappy_v2');
		add_filter('dy_tx_read_transaction', [Dy_Yappy_V2_Store::class, 'read_transaction'], 10, 2);
		add_filter('dy_tx_persist_transaction', [Dy_Yappy_V2_Store::class, 'persist_transaction'], 10, 2);
		add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
	}

	public function init(): void
	{
		$this->name = 'Yappy Online';
		$this->brands = ['Yappy'];
		$this->type = 'alt';
		$this->color = '#fff';
		$this->background_color = '#013685';
		$this->icon = '<img alt="Yappy" width="21" height="12" src="' . esc_url(plugin_dir_url(__DIR__) . 'assets/yappy_direct_icon.svg') . '" />';
		$this->min = max(0.01, (float) dy_get_option('yappy_v2_min', 0.01));
		$this->max = (float) dy_get_option('yappy_v2_max', 9999);
		$this->show = (int) dy_get_option('yappy_v2_show', 0);
		$this->enabled = (string) dy_get_option('yappy_v2_enabled', '0') === '1';
		$this->environment = (string) dy_get_option('yappy_v2_environment', 'sandbox') === 'production' ? 'production' : 'sandbox';
		$prefix = 'yappy_v2_' . $this->environment;
		$this->merchant = (string) dy_get_option($prefix . '_merchant_id', '');
		$this->domain = (string) dy_get_option($prefix . '_domain', '');
		$decoded = base64_decode((string) dy_get_option($prefix . '_secret', ''), true);
		$this->signing_key = $decoded === false ? '' : explode('.', $decoded, 2)[0];
	}

	public function is_active(): bool
	{
		return $this->enabled && $this->merchant !== '' && $this->signing_key !== ''
			&& filter_var($this->domain, FILTER_VALIDATE_URL) !== false
			&& str_starts_with($this->domain, 'https://') && function_exists('openssl_encrypt');
	}

	public function branding(): string
	{
		return '<img alt="Yappy" width="80" height="69" src="' . esc_url(plugin_dir_url(__DIR__) . 'assets/yappy_direct.svg') . '" />';
	}

	public function validate(array $tx): bool
	{
		if (!parent::validate($tx)) return false;
		$service = dy_tx::service($tx);
		$phone = (string) dy_tx::payload_value('phone', '', $tx);
		$calling_code = ltrim((string) dy_tx::payload_value('country_calling_code', '', $tx), '+');
		if ($calling_code !== '507' || !preg_match('/^[0-9]{8}$/D', $phone)) {
			dy_errors::add(__('Yappy requires a Panamanian phone number with eight digits.', 'dycore'));
			return false;
		}
		if (!in_array($service['currency'] ?? '', ['USD', 'PAB'], true)) {
			dy_errors::add(__('Yappy only supports payments in USD or PAB.', 'dycore'));
			return false;
		}
		if (($service['currency_exponent'] ?? null) !== 2 || (int) ($service['amount_minor'] ?? 0) < 1) {
			dy_errors::add(__('Yappy requires a payment amount of at least 0.01 with two decimal places.', 'dycore'));
			return false;
		}
		if ($this->amount($tx) < $this->min || $this->amount($tx) > $this->max) {
			dy_errors::add(__('The Yappy charge is outside the configured payment limits.', 'dycore'));
			return false;
		}
		if (!is_ssl()) {
			dy_errors::add(__('This Yappy checkout requires HTTPS, including in sandbox mode.', 'dycore'));
			return false;
		}
		if (!str_starts_with(rest_url('dy-core/gateways/yappy-v2'), 'https://')) {
			dy_errors::add(__('Yappy payment notifications require an HTTPS callback URL.', 'dycore'));
			return false;
		}
		if (!Dy_Yappy_V2_Store::install()) {
			dy_errors::add(__('Unable to prepare the payment. Please contact us.', 'dycore'), 503);
			return false;
		}
		return true;
	}

	public function process_transaction(array $tx): array
	{
		// Checkout has already persisted its sanitized processing claim.
		$tx = dy_tx::get_stored_tx($tx['tx_id']) ?? $tx;
		$tx['status'] = 'success';
		// A UUID cannot be sent to Yappy (15 alphanumeric characters maximum).
		$order_id = substr(hash('sha256', $tx['tx_id']), 0, 15);
		$token = bin2hex(random_bytes(32));
		$private = Dy_Yappy_V2_Store::seal(['key' => $this->signing_key]);
		$tx['gateway'] = ['id' => $this->id, 'status' => 'pending', 'reference' => '', 'response' => [], 'metadata' => [
			'order_id' => $order_id, 'environment' => $this->environment,
			'payment_amount' => $this->amount($tx),
			'formatted_payment_amount' => dy_tx::service_value('formatted_charge_amount', '', $tx),
			'payment_type' => dy_tx::service_value('payment_type', 'payment', $tx),
			'payment_url' => trailingslashit(home_lang()) . 'dy-tx/' . rawurlencode($tx['tx_id']),
		]];
		$record = [
			'order_id' => $order_id, 'tx_id' => $tx['tx_id'], 'tx' => $tx, 'state' => 'new',
			'environment' => $this->environment, 'merchant' => $this->merchant, 'domain' => $this->domain,
			'credentials' => $private, 'access_hash' => hash('sha256', $token), 'access_expires' => time() + DAY_IN_SECONDS,
			'launch' => '', 'launch_expires' => 0, 'notification' => '',
		];
		if ($private === '' || !$this->set_access_cookie($tx['tx_id'], $token)
			|| !Dy_Yappy_V2_Store::save($record, true)) {
			$tx['status'] = 'error';
			$tx['gateway']['status'] = 'error';
		}
		return $tx;
	}

	protected function set_access_cookie(string $tx_id, string $token): bool
	{
		return setcookie('dy_yappy_' . $tx_id, $token, [
			'expires' => time() + DAY_IN_SECONDS, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax',
		]);
	}

	private function access_token(array $record): string
	{
		$token = (string) secure_cookie('dy_yappy_' . $record['tx_id'], '');
		return $record['access_expires'] >= time() && preg_match('/^[a-f0-9]{64}$/D', $token)
			&& hash_equals($record['access_hash'], hash('sha256', $token)) ? $token : '';
	}

	private function csrf(array $record, string $token): string
	{
		return hash_hmac('sha256', $record['tx_id'] . $token, wp_salt('nonce'));
	}

	private function owned_record(WP_REST_Request $request): ?array
	{
		$id = $request->get_param('tx_id');
		if (!is_string($id) || !wp_is_uuid($id, 4)) return null;
		$record = Dy_Yappy_V2_Store::find($id, true);
		if ($record === null || ($token = $this->access_token($record)) === '') return null;
		return hash_equals($this->csrf($record, $token), $request->get_header('x-dy-yappy-token')) ? $record : null;
	}

	public function client_permission(WP_REST_Request $request): bool|WP_Error
	{
		$record = $this->owned_record($request);
		if (Dy_Yappy_V2_Store::read_failed()) return new WP_Error('yappy_unavailable', __('Payment status is temporarily unavailable.', 'dycore'), ['status' => 503]);
		return $record !== null;
	}

	private function response(array $data, int $status = 200): WP_REST_Response
	{
		$response = new WP_REST_Response($data, $status);
		$response->set_headers(['Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0', 'Pragma' => 'no-cache']);
		return $response;
	}

	private function error(string $message, int $status = 409): WP_REST_Response
	{
		return $this->response(['success' => false, 'message' => $message], $status);
	}

	public function start(WP_REST_Request $request): WP_REST_Response
	{
		$record = $this->owned_record($request);
		if (Dy_Yappy_V2_Store::read_failed()) return $this->error(__('Payment is temporarily unavailable.', 'dycore'), 503);
		if ($record === null) return $this->error(__('Open this payment in the browser used for checkout.', 'dycore'), 403);
		$id = $record['tx_id'];
		if (!Dy_Yappy_V2_Store::lock($id)) return $this->error(__('Payment is being processed. Please wait.', 'dycore'));
		try {
			$record = Dy_Yappy_V2_Store::find($id, true);
			if ($record === null || !dy_tx::validate($id, $record['tx']) || $record['tx']['status'] !== 'success') {
				return $this->error(__('The payment request is unavailable.', 'dycore'), 503);
			}
			if ($record['state'] === 'ready' && $record['launch_expires'] >= time()) {
				$launch = Dy_Yappy_V2_Store::open($record['launch']);
				if ($launch !== null) return $this->response(['success' => true, 'body' => $launch]);
			}
			if ($record['state'] !== 'new') return $this->error(__('This order has already been started. Wait for its payment status or contact us before paying again.', 'dycore'));
			// Disabling the gateway prevents new API orders; existing IPNs still work.
			if (!$this->is_active()) return $this->error(__('Yappy is temporarily unavailable.', 'dycore'), 503);
			$merchant = $this->api($record, '/payments/validate/merchant', ['merchantId' => $record['merchant'], 'urlDomain' => $record['domain']]);
			if (!is_string($merchant['token'] ?? null) || $merchant['token'] === '' || !is_numeric($merchant['epochTime'] ?? null)) {
				return $this->error(__('Yappy is temporarily unavailable. Please try again shortly.', 'dycore'), 502);
			}
			// Persist intent before the request: timeout/crash must never cause a second order.
			$record['state'] = 'creating';
			if (!Dy_Yappy_V2_Store::save($record)) return $this->error(__('Unable to store the payment request.', 'dycore'), 503);
			$amount = (int) dy_tx::service_value('amount_minor', 0, $record['tx']);
			// The source supplies the final charge. It already includes any discounts/deposit.
			$total = intdiv($amount, 100) . '.' . str_pad((string) ($amount % 100), 2, '0', STR_PAD_LEFT);
			$launch = $this->api($record, '/payments/payment-wc', [
				'merchantId' => $record['merchant'], 'orderId' => $record['order_id'], 'domain' => $record['domain'],
				'paymentDate' => (int) $merchant['epochTime'], 'aliasYappy' => (string) dy_tx::payload_value('phone', '', $record['tx']),
				'ipnUrl' => rest_url('dy-core/gateways/yappy-v2'), 'discount' => '0.00', 'taxes' => '0.00', 'subtotal' => $total, 'total' => $total,
			], $merchant['token']);
			$reference = $launch['transactionId'] ?? null;
			$valid = (is_string($reference) || is_int($reference)) && (string) $reference !== '';
			foreach (['token', 'documentName'] as $field) {
				if (!is_string($launch[$field] ?? null) || $launch[$field] === '') $valid = false;
			}
			if (!$valid) {
				$record['state'] = 'uncertain';
				Dy_Yappy_V2_Store::save($record);
				return $this->error(__('Yappy did not confirm the order creation. Contact us before attempting another payment.', 'dycore'), 502);
			}
			$launch = array_intersect_key($launch, array_flip(['transactionId', 'token', 'documentName']));
			$record['launch'] = Dy_Yappy_V2_Store::seal($launch);
			$record['launch_expires'] = time() + 300;
			$record['state'] = 'ready';
			$record['tx']['gateway']['reference'] = sanitize_text_field((string) $launch['transactionId']);
			if ($record['launch'] === '' || !Dy_Yappy_V2_Store::save($record)) {
				return $this->error(__('Unable to store the payment response. Contact us before paying again.', 'dycore'), 503);
			}
			return $this->response(['success' => true, 'body' => $launch]);
		} finally {
			Dy_Yappy_V2_Store::lock($id, true);
		}
	}

	/** Do not retain full responses, merchant tokens or remote error messages. */
	private function api(array $record, string $path, array $body, string $token = ''): array
	{
		$headers = ['Content-Type' => 'application/json', 'Accept' => 'application/json'];
		if ($token !== '') $headers['Authorization'] = $token;
		$response = wp_remote_post(self::HOSTS[$record['environment']]['api'] . $path,
			['headers' => $headers, 'body' => wp_json_encode($body), 'timeout' => 25, 'redirection' => 0]);
		if (is_wp_error($response) || wp_remote_retrieve_response_code($response) < 200 || wp_remote_retrieve_response_code($response) >= 300) return [];
		$data = json_decode(wp_remote_retrieve_body($response), true);
		if (!is_array($data) || !is_array($data['body'] ?? null)) return [];
		// The supplied API spec does not define a success code. Required body fields are checked by the caller.
		$code = $data['status']['code'] ?? '';
		return is_scalar($code) && !str_starts_with((string) $code, 'E') ? $data['body'] : [];
	}

	public function status(WP_REST_Request $request): WP_REST_Response
	{
		$record = $this->owned_record($request);
		if (Dy_Yappy_V2_Store::read_failed()) return $this->error(__('Payment status is temporarily unavailable.', 'dycore'), 503);
		if ($record === null) return $this->error(__('Payment access expired. Please contact us.', 'dycore'), 403);
		return $this->response(['success' => true, 'status' => $record['tx']['gateway']['status'], 'state' => $record['state']]);
	}

	public function ipn(WP_REST_Request $request): WP_REST_Response
	{
		$params = $request->get_query_params();
		foreach (['orderId', 'status', 'domain'] as $field) {
			if (!is_string($params[$field] ?? null)) return $this->response(['success' => false], 400);
		}
		$hash = $params['hash'] ?? $params['Hash'] ?? null;
		if (!is_string($hash) || !preg_match('/^[a-fA-F0-9]{64}$/D', $hash)
			|| (isset($params['hash'], $params['Hash']) && $params['hash'] !== $params['Hash'])
			|| !preg_match('/^[a-zA-Z0-9]{1,15}$/D', $params['orderId'])
			|| !in_array($params['status'], ['E', 'R', 'C', 'X'], true)) return $this->response(['success' => false], 400);
		$record = Dy_Yappy_V2_Store::find($params['orderId']);
		if (Dy_Yappy_V2_Store::read_failed()) return $this->response(['success' => false], 503);
		$credentials = $record === null ? null : Dy_Yappy_V2_Store::open($record['credentials']);
		if ($credentials === null || $params['domain'] !== $record['domain']
			|| !hash_equals(hash_hmac('sha256', $params['orderId'] . $params['status'] . $params['domain'], $credentials['key']), strtolower($hash))) {
			return $this->response(['success' => false], 403);
		}
		$id = $record['tx_id'];
		if (!Dy_Yappy_V2_Store::lock($id)) return $this->response(['success' => false], 503);
		try {
			$record = Dy_Yappy_V2_Store::find($id, true);
			if (Dy_Yappy_V2_Store::read_failed()) return $this->response(['success' => false], 503);
			if ($record === null || $record['state'] === 'new' || !dy_tx::validate($id, $record['tx'])) return $this->response(['success' => false], 409);
			$status = match ($params['status']) { 'E' => 'approved', 'R' => 'declined', 'C' => 'cancelled', 'X' => 'expired' };
			$tx = $record['tx'];
			if ($record['state'] !== 'approved' && ($status === 'approved' || !in_array($record['state'], ['declined', 'cancelled', 'expired'], true))) {
				$tx['gateway']['status'] = $status;
				$tx['gateway']['response'] = ['status' => $params['status']];
				$tx = apply_filters('dy_gateway_settled_transaction', $tx);
				if (!dy_tx::update($tx)) return $this->response(['success' => false], 503);
				$record = Dy_Yappy_V2_Store::find($id, true);
				if ($record === null) return $this->response(['success' => false], 503);
			}
			if ($record['state'] === 'approved' && $record['notification'] === '') {
				// Claim before external side effects. Retries cannot send duplicate paid notices.
				$record['notification'] = 'claimed';
				if (!Dy_Yappy_V2_Store::save($record)) return $this->response(['success' => false], 503);
				do_action('dy_gateway_payment_approved', $record['tx']);
				$record['notification'] = 'attempted';
				if (!Dy_Yappy_V2_Store::save($record)) return $this->response(['success' => false], 503);
			}
			return $this->response(['success' => true]);
		} finally {
			Dy_Yappy_V2_Store::lock($id, true);
		}
	}

	public function confirmation_result(array $result, array $tx): array
	{
		if ((dy_tx::gateway($tx)['id'] ?? '') !== $this->id) return $result;
		$status = $tx['gateway']['status'];
		$result['title'] = $status === 'approved' ? __('Payment confirmed', 'dycore') : __('Yappy payment', 'dycore');
		if ($status !== 'pending') {
			$result['content'] = '<p>' . esc_html($status === 'approved' ? __('Your Yappy payment was confirmed.', 'dycore')
				: __('This payment was not completed. Please contact us before making another payment.', 'dycore')) . '</p>';
			return $result;
		}
		$amount = (string) dy_tx::service_value('formatted_charge_amount', '', $tx);
		$result['content'] = '<p>' . esc_html(sprintf(__('Complete your Yappy payment of %s in the browser used for checkout.', 'dycore'), $amount)) . '</p>';
		if (($tx['gateway']['metadata']['environment'] ?? '') === 'sandbox') {
			$result['content'] = '<p><strong>' . esc_html(__('Sandbox: test payment only.', 'dycore')) . '</strong></p>' . $result['content'];
		}
		$result['content'] .= '<p><a href="' . esc_url($tx['gateway']['metadata']['payment_url']) . '">' . esc_html(__('Open payment', 'dycore')) . '</a></p>';
		$record = Dy_Yappy_V2_Store::find($tx['tx_id'], true);
		if (Dy_Confirmation_Page::is_confirmation() && $record !== null && $this->access_token($record) !== '') {
			$result['content'] .= '<div id="dy-yappy-v2"><btn-yappy theme="blue"></btn-yappy><p role="status" aria-live="polite"></p></div>';
		}
		return $result;
	}

	public function enqueue_scripts(): void
	{
		if (!Dy_Confirmation_Page::is_confirmation()) return;
		$tx = dy_tx::current_transaction();
		if ($tx === null || ($tx['gateway']['id'] ?? '') !== $this->id || $tx['gateway']['status'] !== 'pending') return;
		$record = Dy_Yappy_V2_Store::find($tx['tx_id'], true);
		if ($record === null || ($token = $this->access_token($record)) === '') return;
		wp_enqueue_script('dy-yappy-v2', plugin_dir_url(__FILE__) . 'yappy-v2.js', [], '1.0.0', true);
		wp_localize_script('dy-yappy-v2', 'dyYappyV2', [
			'txId' => $tx['tx_id'], 'csrf' => $this->csrf($record, $token), 'sdkUrl' => self::HOSTS[$record['environment']]['sdk'],
			'startUrl' => rest_url('dy-core/gateways/yappy-v2/start'), 'statusUrl' => rest_url('dy-core/gateways/yappy-v2/status'),
			'waiting' => __('Waiting for Yappy to confirm your payment.', 'dycore'),
			'unavailable' => __('Yappy is temporarily unavailable. Please try again shortly.', 'dycore'),
			'uncertain' => __('This order has already been started. Wait for its payment status or contact us before paying again.', 'dycore'),
		]);
	}

	public function settings_init(): void
	{
		$group = 'yappy_v2_settings';
		add_settings_section($group, __('Yappy Online settings', 'dycore'), '__return_false', $group);
		$fields = [
			'enabled' => [__('Enabled', 'dycore'), ['0' => __('No', 'dycore'), '1' => __('Yes', 'dycore')]],
			'environment' => [__('Environment', 'dycore'), ['sandbox' => 'Sandbox', 'production' => 'Production']],
			'min' => [__('Minimum amount', 'dycore'), 'number'], 'max' => [__('Maximum amount', 'dycore'), 'number'],
			'show' => [__('Show', 'dycore'), ['0' => __('Full Payments and Deposits', 'dycore'), '1' => __('Only Deposits', 'dycore')]],
		];
		foreach (['sandbox', 'production'] as $environment) {
			foreach (['merchant_id' => 'Merchant ID', 'domain' => 'Registered HTTPS domain (exact value)', 'secret' => 'Base64 IPN secret'] as $key => $label) {
				$fields[$environment . '_' . $key] = [ucfirst($environment) . ' — ' . $label, $key === 'secret' ? 'password' : 'text'];
			}
		}
		foreach ($fields as $suffix => [$label, $type]) {
			$key = 'yappy_v2_' . $suffix;
			register_setting($group, $key, ['sanitize_callback' => static function (mixed $value) use ($key, $type): string {
				$value = is_scalar($value) ? sanitize_text_field((string) $value) : '';
				return $type === 'password' && $value === '' ? (string) dy_get_option($key, '') : $value;
			}]);
			add_settings_field($key, $label, [$this, 'setting_field'], $group, $group, ['key' => $key, 'type' => $type]);
		}
	}

	public function setting_field(array $args): void
	{
		$key = $args['key'];
		$type = $args['type'];
		$value = (string) dy_get_option($key, match ($key) { 'yappy_v2_min' => '0.01', 'yappy_v2_max' => '9999', default => '' });
		if (is_array($type)) {
			echo '<select name="' . esc_attr($key) . '">';
			foreach ($type as $option => $label) echo '<option value="' . esc_attr($option) . '"' . ((string) $option === $value ? ' selected' : '') . '>' . esc_html($label) . '</option>';
			echo '</select>';
			return;
		}
		$autocomplete = $type === 'password' ? 'new-password' : 'off';
		echo '<input class="regular-text" name="' . esc_attr($key) . '" type="' . esc_attr($type) . '" value="' . esc_attr($value) . '" step="0.01" autocomplete="' . esc_attr($autocomplete) . '" />';
	}

	public function add_settings_page(): void
	{
		add_submenu_page((string) apply_filters('dy_gateway_settings_parent', 'dy-core'), 'Yappy Online', '💸 Yappy Online', 'manage_options', $this->id, [$this, 'settings_page']);
	}

	public function settings_page(): void
	{
		echo '<div class="wrap"><h1>Yappy Online</h1><p>IPN: <code>' . esc_html(rest_url('dy-core/gateways/yappy-v2')) . '</code></p><form action="options.php" method="post">';
		settings_fields('yappy_v2_settings');
		do_settings_sections('yappy_v2_settings');
		submit_button();
		echo '</form></div>';
	}
}
