<?php

if (!defined('WPINC')) exit;

class cuanto extends Dy_Gateway
{
	private static array $cache = [];

	public function __construct(string $settings_parent = 'dy-core')
	{
		$this->register('cuanto');
	}
	public function init(): void
	{
		$this->order_status = 'pending';
		$this->name = 'Cuanto.app';
		$this->brands = ['Mastercard', 'Visa'];
		$this->cards_accepted = implode_last($this->brands, __('or', 'dycore'));
		$this->type = 'card-off-site';
		$this->domain = 'cuanto.app';		
		$this->username = dy_get_option($this->id);
		$this->show = (int) dy_get_option($this->id . '_show', 0);
		$this->max  = (float) dy_get_option($this->id . '_max', 0.0);
		$this->color = '#000';
		$this->background_color = '#8CD0C5';
		$this->plugin_dir_url = plugin_dir_url(__DIR__);
		$this->icon = '<span class="dashicons dashicons-cart"></span>';
	}

	public function is_active(): bool
	{
		$output = false;
		$cache_key = $this->id.'_is_active';
		
        if (array_key_exists($cache_key, self::$cache)) {
            return self::$cache[$cache_key];
        }

		if(!empty($this->username))
		{
			$output = true;
		}

        //store output in $cache
        self::$cache[$cache_key] = $output;

		return $output;
	}

	public function settings_init(): void
	{		
		register_setting($this->id . '_settings', $this->id, 'sanitize_user');
		register_setting($this->id . '_settings', $this->id . '_show', 'intval');
		register_setting($this->id . '_settings', $this->id . '_max', 'floatval');
		
		add_settings_section(
			$this->id . '_settings_section', 
			__( 'General Settings', 'dycore' ), 
			'', 
			$this->id . '_settings'
		);
		
		add_settings_field( 
			$this->id, 
			__( 'Username', 'dycore' ), 
			['dy_input_option', 'text'], 
			$this->id . '_settings', 
			$this->id . '_settings_section',
			[
				'key' => $this->id,
			]
		);

		add_settings_field( 
			$this->id . '_max', 
			__( 'Max. Amount', 'dycore' ), 
			['dy_input_option', 'price'], 
			$this->id . '_settings', 
			$this->id . '_settings_section',
			[
				'key'    => $this->id . '_max',
				'append' => currency_symbol(),
			]
		);

		add_settings_field( 
			$this->id . '_show', 
			__( 'Show', 'dycore' ), 
			['dy_select_option', 'custom'], 
			$this->id . '_settings', 
			$this->id . '_settings_section', 
			[
				'key' => $this->id . '_show',
				'options' => [
					0 => __('Full Payments and Deposits', 'dycore'),
					1 => __('Only Deposits', 'dycore')
				]
			]
		);		
	}

	public function add_settings_page(): void
	{
		add_submenu_page( (string) apply_filters('dy_gateway_settings_parent', 'dy-core'), $this->name, '💸 '.$this->name, 'manage_options', $this->id, [$this, 'settings_page']);
	}

	public function settings_page(): void
		 { 
		?><div class="wrap">
		<form action="options.php" method="post">
			
			<h1><?php esc_html_e($this->name); ?></h1>	
			<?php
			settings_fields( $this->id . '_settings' );
			do_settings_sections( $this->id . '_settings' );
			submit_button();
			?>			
		</form>
		
		<?php
	}

	public function branding(): string
	{
		$output = '<p><img src="'.esc_url($this->plugin_dir_url.'assets/visa-mastercard.svg').'" width="250" height="50" /></p>';
		$output .= '<p class="large text-muted">'.esc_html(sprintf(__('Pay with %s thanks to %s', 'dycore'), $this->cards_accepted, $this->name)).'</p>';
		return $output;
	}

	public function process_transaction(array $tx): array
	{
		$payment_amount = (float) $this->amount($tx);
		$tx['gateway'] = [
			'id' => $this->id,
			'status' => 'pending',
			'reference' => null,
			'response' => [],
			'metadata' => [
				'payment_url' => esc_url_raw($this->payment_url($payment_amount, (string) $this->domain, (string) $this->username)),
				'username' => sanitize_text_field((string) $this->username),
				'payment_amount' => $payment_amount,
				'formatted_payment_amount' => (string) dy_tx::service_value('formatted_charge_amount', '', $tx),
				'payment_type' => (string) dy_tx::service_value('payment_type', 'payment', $tx),
			],
		];

		return $tx;
	}

	public function confirmation_result(array $result, array $tx): array
	{
		$gateway = dy_tx::gateway($tx);
		if (($gateway['id'] ?? '') !== $this->id) {
			return $result;
		}

		$metadata = is_array($gateway['metadata'] ?? null) ? $gateway['metadata'] : [];
		$amount = (float) ($metadata['payment_amount'] ?? dy_tx::service_value('payment_amount', 0, $tx));

		$result['content'] = $this->payment_message(
			'',
			$amount,
			($metadata['payment_type'] ?? '') === 'deposit',
			(string) ($metadata['payment_url'] ?? ''),
			(string) ($metadata['formatted_payment_amount'] ?? '')
		);

		return $result;
	}

	private function payment_message(
		string $message,
		float $payment_amount,
		bool $is_deposit,
		string $payment_url,
		string $formatted_amount = ''
	): string {
		$amount = $formatted_amount !== ''
			? $formatted_amount
			: wrap_money_full($payment_amount);
		$label = $is_deposit
			? __('deposit of', 'dycore')
			: __('full payment of', 'dycore');

		$message .= '<p class="large">' . esc_html(__('To complete the payment please click on the following link. We are also going to send you this same information by email.', 'dycore')) . '</p>';
		$message .= '<p class="large">' . esc_html(sprintf(__('Please send us the %s %s to complete this payment.', 'dycore'), $label, $amount)) . '</p>';
		$message .= '<p style="margin-bottom: 40px;"><a target="_blank" rel="noopener noreferrer" style="border: 16px solid #8CD0C5; text-align: center; background-color: ' . esc_html($this->background_color) . '; color: ' . esc_html($this->color) . '; font-size: 18px; line-height: 18px; display: block; width: 100%; box-sizing: border-box; text-decoration: none; font-weight: 900;" href="' . esc_url($payment_url) . '"><span class="dashicons dashicons-cart"></span> ' . esc_html(sprintf(__('Pay with %s', 'dycore'), $this->cards_accepted)) . '</a></p>';

		return $message;
	}

	private function payment_url(float $payment_amount, string $domain, string $username): string
	{
		return 'https://' . $domain . '/' . $username . '/c/' . (int) round($payment_amount * 100);
	}
}
