<?php

if (!defined('WPINC')) exit;

class yappy_direct extends Dy_Gateway
{
	private static array $cache = [];

	public function __construct(string $settings_parent = 'dy-core')
	{
		$this->register('yappy_direct');
	}
	public function init(): void
	{
		$this->order_status = 'pending';
		$this->name = 'Yappy';
		$this->brands = [$this->name];
			
		$this->gateway_short_name = 'yappy';
		$this->type = 'alt';	
		$this->number = dy_get_option($this->id);
		$this->business = dy_get_option($this->id . '_business');
		$this->max  = (float) dy_get_option($this->id . '_max', 0.0);
		$this->show = (int) dy_get_option($this->id . '_show', 0);
		$this->qrcode = dy_get_option($this->id . '_qrcode');
		$this->color = '#fff';
		$this->background_color = '#013685';
		$this->plugin_dir_url = plugin_dir_url(__DIR__);
		$this->icon = '<img alt="yappy" width="21" height="12" src="'.esc_url($this->plugin_dir_url.'assets/'.$this->id.'_icon.svg').'" />';
	}

	public function is_active(): bool
	{
		$output = false;
		$cache_key = $this->id . '_is_active';
		
        if (array_key_exists($cache_key, self::$cache)) {
            return self::$cache[$cache_key];
        }

		if(!empty($this->number) || !empty($this->business))
		{
			$output = true;
		}

        //store output in $cache
        self::$cache[$cache_key] = $output;

		return $output;
	}

	public function settings_init(): void
	{
		//Yappy
		
		register_setting($this->id . '_settings', $this->id, 'intval');
		register_setting($this->id . '_settings', $this->id . '_business', 'sanitize_text_field');
		register_setting($this->id . '_settings', $this->id . '_show', 'intval');
		register_setting($this->id . '_settings', $this->id . '_max', 'floatval');
		register_setting($this->id . '_settings', $this->id . '_qrcode', 'esc_url');
		
		add_settings_section(
			$this->id . '_settings_section', 
			__( 'General Settings', 'dycore' ), 
			'', 
			$this->id . '_settings'
		);
		
		add_settings_field( 
			$this->id, 
			__( 'Yappy Cell Phone Number', 'dycore' ), 
			['dy_input_option', 'int'], 
			$this->id . '_settings', 
			$this->id . '_settings_section',
			[
				'key'    => $this->id,
				'append' => ' #',
			]
		);	
		
		add_settings_field( 
			$this->id . '_business', 
			__( 'Yappy Business Name', 'dycore' ), 
			['dy_input_option', 'text'], 
			$this->id . '_settings', 
			$this->id . '_settings_section', 
			[
				'key' => $this->id . '_business',
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
					1 => __('Only Deposits', 'dycore'),
				],
			]
		);
		
		add_settings_field( 
			$this->id . '_qrcode', 
			__( 'QR Code Url', 'dycore' ), 
			['dy_input_option', 'url'], 
			$this->id . '_settings', 
			$this->id . '_settings_section', 
			[
				'key' => $this->id . '_qrcode',
			]
		);
	}

	public function add_settings_page(): void
	{
		add_submenu_page((string) apply_filters('dy_gateway_settings_parent', 'dy-core'), $this->name, '💸 '. $this->name, 'manage_options', $this->id, [$this, 'settings_page']);
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
		return '<img src="'.esc_url($this->plugin_dir_url.'assets/'.$this->id.'.svg').'" width="80" height="69" alt="'.esc_attr($this->name).'" />';
	}

	public function process_transaction(array $tx): array
	{
		$destination = (string) ($this->business !== ''
			? '@' . strtoupper((string) $this->business)
			: $this->number);
		$payment_amount = (float) $this->amount($tx);
		$tx['gateway'] = [
			'id' => $this->id,
			'status' => 'pending',
			'reference' => null,
			'response' => [],
			'metadata' => [
				'destination' => sanitize_text_field($destination),
				'qrcode_url' => esc_url_raw((string) $this->qrcode),
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
		$is_deposit = ($metadata['payment_type'] ?? '') === 'deposit';
		$qrcode = (string) ($metadata['qrcode_url'] ?? '');
		$formatted_amount = (string) ($metadata['formatted_payment_amount'] ?? '');
		$content = $this->payment_message(
			$amount,
			$is_deposit,
			(string) ($metadata['destination'] ?? ''),
			$formatted_amount
		);

		if ($qrcode !== '') {
			$content .= $this->qrcode_message($amount, $is_deposit, $qrcode, $formatted_amount);
		}

		$result['title'] = esc_html(sprintf(__('%s Payment Instructions', 'dycore'), $this->name));
		$result['content'] = $content;

		return $result;
	}

	private function payment_message(
		float $payment_amount,
		bool $is_deposit,
		string $destination_text,
		string $formatted_amount = ''
	): string {
		$destination = '<strong>' . esc_html($destination_text) . '</strong>';
		$amount = $formatted_amount !== '' ? $formatted_amount : wrap_money_full($payment_amount);
		$label = $is_deposit ? esc_html(__('deposit', 'dycore')) : esc_html(__('payment', 'dycore'));
		$text = str_starts_with($destination_text, '@')
			? sprintf(__('A. Find us at the Yappy Business Directory by the company name %s and then send us the %s (%s).', 'dycore'), $destination, $label, $amount)
			: sprintf(__('A. Send the %s (%s) to the Yappy number %s.', 'dycore'), $label, $amount, $destination);

		$message = '<p><strong>' . esc_html(__('Step', 'dycore')) . ' 1:</strong></p>';
		$message .= '<p class="large dy_pad padding-10">' . esc_html(__('Enter your Banco General Yappy app.', 'dycore')) . '</p>';
		$message .= '<p><strong>' . esc_html(__('Step', 'dycore')) . ' 2:</strong></p>';
		$message .= '<p class="large dy_pad padding-10">' . $text . '</p>';

		return $message;
	}

	private function qrcode_message(
		float $payment_amount,
		bool $is_deposit,
		string $qrcode,
		string $formatted_amount = ''
	): string
	{
		$amount = $formatted_amount !== '' ? $formatted_amount : wrap_money_full($payment_amount);
		$label = $is_deposit ? __('deposit', 'dycore') : __('payment', 'dycore');
		$content = '<p class="large dy_pad padding-10">' . esc_html(sprintf(__('B. Alternatively, you can also scan the following QR code within your Banco General Yappy app and then send us the %s (%s).', 'dycore'), $label, $amount)) . '</p>';

		return $content . '<p><img width="250" height="250" src="' . esc_url($qrcode) . '" alt="yappy qrcode"/></p>';
	}
}
