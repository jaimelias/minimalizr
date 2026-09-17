<?php

if (!defined('WPINC')) exit;

/** Render-only views. HTML slots are supplied at runtime and never stored in a transaction. */
final class Dy_Checkout_Form
{
	private static bool $security_enqueued = false;
	public static function render(array $view): string
	{
		self::enqueue();
		$view += ['id' => 'dy_checkout_form', 'has_gateway' => false, 'title' => __('Contact Us', 'dycore'),
			'submit_label' => __('Submit', 'dycore'), 'fields' => [], 'card_notice' => '', 'terms' => '', 'inquiry' => ''];
		ob_start();
		require __DIR__ . '/partials/checkout-form.php';
		return (string) ob_get_clean();
	}

	public static function page(array $view): string
	{
		ob_start();
		require __DIR__ . '/partials/checkout-page.php';
		return (string) ob_get_clean();
	}

	public static function card(): void { require __DIR__ . '/partials/cc-form.php'; }
	public static function crypto(): void { require __DIR__ . '/partials/crypto-form.php'; }

	public static function buttons(array $gateways): string
	{
		$output = [];
		foreach ($gateways as $id => $gateway) {
			$output[] = sprintf('<button data-networks="%s" data-intent="%s" data-type="%s" data-id="%s" data-branding="%s" style="color: %s; background-color: %s;" class="pure-button bottom-20 rounded" type="button">%s %s</button>',
				esc_attr(wp_json_encode($gateway['networks'] ?? [])), esc_attr($gateway['intent'] ?? 'payment'),
				esc_attr($gateway['type']), esc_attr($id), esc_attr($gateway['branding']),
				esc_attr($gateway['color']), esc_attr($gateway['background_color']), $gateway['icon'],
				esc_html(implode_last($gateway['brands'], __('or', 'dycore'))));
		}
		return implode(' ', $output);
	}

	public static function enqueue_security(): void
	{
		if (self::$security_enqueued) return;
		self::$security_enqueued = true;
		$core_url = plugin_dir_url(dirname(__DIR__) . '/loader.php');
		$version = defined('DY_CORE_VERSION') ? DY_CORE_VERSION : null;
		wp_enqueue_script('cloudflare-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit', [], null, true);
		wp_add_inline_script('cloudflare-turnstile', 'const turnstileArgs=' . wp_json_encode([
			'turnstileSiteKey' => get_turnstile_site_key(),
			'translations' => ['contact_support' => __('Contact Support', 'dycore')],
		]) . ';', 'before');
		wp_enqueue_script('cloudflare-turnstile-widgets', $core_url . 'js/turnstile-widgets.js', ['cloudflare-turnstile'], $version, true);
	}

	public static function enqueue(): void
	{
		self::enqueue_security();
		$core_url = plugin_dir_url(dirname(__DIR__) . '/loader.php');
		$version = defined('DY_CORE_VERSION') ? DY_CORE_VERSION : null;
		wp_enqueue_script('dy-core-request-form-utilities', $core_url . 'js/request-form-utilities.js', ['jquery', 'landing-cookies', 'dy-core-utilities'], $version, true);
		wp_enqueue_script('dy-core-checkout', plugin_dir_url(__FILE__) . 'checkout-form.js',
			['jquery', 'dy-core-utilities', 'dy-core-request-form-utilities', 'cloudflare-turnstile', 'cloudflare-turnstile-widgets'],
			$version, true);
		wp_localize_script('dy-core-checkout', 'dyCheckoutArgs', [
			'submit_error' => __('Submission error', 'dycore'), 'correct_form' => __('Please correct the form.', 'dycore'),
		]);
	}
}
