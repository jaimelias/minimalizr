<?php

if (!defined('WPINC')) exit;

class stable_coins extends Dy_Gateway
{
	private static array $cache = [];
	private const NETWORKS = array(
		'trx' => array('name' => 'Tron (TRC-20)'),
		'eth' => array('name' => 'Ethereum (ERC-20) Network'),
		'bsc' => array('name' => 'Binance Smart Chain (BEP-20)'),
		'matic' => array('name' => 'Poligon (MATIC) Network'),
		'sol' => array('name' => 'Solana Network'),
		'avax' => array('name' => 'Avalanche Network'),
	);

	private const ASSETS = array(
		'usdt' => array(
			'name' => 'Tether (USDT)',
			'background_color' => '#50AF95',
			'networks' => array('trx', 'eth', 'bsc', 'matic', 'sol', 'avax'),
		),
		'usdc' => array(
			'name' => 'USD Coin (USDC)',
			'background_color' => '#2775CA',
			'networks' => array('eth', 'bsc', 'matic', 'sol', 'avax'),
		),
	);


	public function __construct(string $settings_parent = 'dy-core', string $id = 'usdt')
	{
		if (!isset(self::ASSETS[$id])) throw new InvalidArgumentException('Unsupported stable USD gateway.');
		$this->register($id);
	}
	public function init(): void
    {
        $config = self::ASSETS[$this->id];

        $this->order_status = 'pending';
        $this->name = $config['name'];
        $this->brands = array($this->name);
        $this->type = 'crypto';
        $this->all_networks = $this->get_all_networks();
        $this->enabled_networks = $this->get_enabled_networks();
        $this->show = (int) dy_get_option($this->id . '_show', 0);
        $this->max  = (float) dy_get_option($this->id . '_max', 0.0);
        $this->color = '#fff';
        $this->background_color = $config['background_color'];
        $this->plugin_dir_url = plugin_dir_url(__DIR__);

        $this->icon = sprintf(
            '<img width="15" height="15" src="%s" alt="%s" />',
            esc_url($this->plugin_dir_url . 'assets/' . $this->id . '_icon.svg'),
            esc_attr($this->name)
        );
    }

	public function get_all_networks(): array
    {
        $networks = [];

        foreach (self::ASSETS[$this->id]['networks'] as $network_id) {
            $networks[$network_id] = self::NETWORKS[$network_id];
        }

        return $networks;
    }

	public function get_enabled_networks(): array
	{
		$output = [];

		foreach($this->all_networks as $key => $value)
		{
			if(!empty(dy_get_option($this->id . '_' . $key)))
			{
				$output[$key] = $value;
			}
		}

		return $output;
	}

	public function is_active(): bool
	{
		$output = false;
		$cache_key = $this->id.'_is_active';

		
        if (array_key_exists($cache_key, self::$cache)) {
            return self::$cache[$cache_key];
        }

		$active_networks = false;

		foreach (array_keys($this->all_networks) as $key) {
			if (!empty(dy_get_option("{$this->id}_{$key}"))) {
				$active_networks = true;
				break;
			}
		}

		if($active_networks)
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

		foreach($this->all_networks as $key => $value)
		{
			register_setting($this->id . '_settings', $this->id . '_' . $key, 'sanitize_user');
		}
		
		add_settings_section(
			$this->id . '_settings_section', 
			__( 'General Settings', 'dycore' ), 
			'', 
			$this->id . '_settings'
		);
	
		add_settings_field(
			$this->id . '_max',
			__('Max. Amount', 'dycore'),
			['dy_input_option', 'price'],
			$this->id . '_settings',
			$this->id . '_settings_section',
			[
				'key' => $this->id . '_max',
				'append' => currency_symbol(),
			]
		);

		add_settings_field(
			$this->id . '_show',
			__('Show', 'dycore'),
			['dy_select_option', 'custom'],
			$this->id . '_settings',
			$this->id . '_settings_section',
			[
				'key' => $this->id . '_show',
				'options' => 				[
					0 => __('Full Payments and Deposits', 'dycore'),
					1 => __('Only Deposits', 'dycore'),
				]
			]
		);
		
		foreach ($this->all_networks as $key => $value) {
			$setting_key = $this->id . '_' . $key;

			add_settings_field(
				$setting_key,
				sprintf( __('%s Contract Address', 'dycore'), $value['name'] ),
				['dy_input_option', 'text'],
				$this->id . '_settings',
				$this->id . '_settings_section',
				[
					'key'   => $setting_key,
					'style' => 'width: 450px;',
				]
			);
		}

	}

	public function add_settings_page(): void
	{
		add_submenu_page( (string) apply_filters('dy_gateway_settings_parent', 'dy-core'), $this->name, '💸 '. $this->name, 'manage_options', $this->id, [$this, 'settings_page']);
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
		return '<img src="'.esc_url($this->plugin_dir_url.'assets/'.$this->id.'.svg').'" width="50" height="50" alt="'.esc_attr($this->name).'" />';
	}

	public function process_transaction(array $tx): array
	{
		$network = secure_post('dy_network', '', 'sanitize_key');
		$network_config = $this->enabled_networks[$network] ?? [];
		$payment_amount = (float) $this->amount($tx);

		$tx['gateway'] = [
			'id' => $this->id,
			'status' => 'pending',
			'reference' => null,
			'response' => [],
			'metadata' => [
				'network_id' => $network,
				'network_name' => sanitize_text_field((string) ($network_config['name'] ?? '')),
				'network_address' => sanitize_text_field((string) dy_get_option($this->id . '_' . $network)),
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
		$asset_name = (string) $this->name;

		$result['title'] = esc_html(sprintf(__('You have chosen %s as your payment method!', 'dycore'), $asset_name));
		$result['content'] = $this->payment_message(
			'',
			$amount,
			($metadata['payment_type'] ?? '') === 'deposit',
			$asset_name,
			(string) ($metadata['network_name'] ?? ''),
			(string) ($metadata['network_address'] ?? ''),
			(string) ($metadata['formatted_payment_amount'] ?? '')
		);

		return $result;
	}

	private function payment_message(
		string $message,
		float $payment_amount,
		bool $is_deposit,
		string $asset_name,
		string $network_name,
		string $address,
		string $formatted_amount = ''
	): string {
		$amount = $formatted_amount !== '' ? $formatted_amount : wrap_money_full($payment_amount);
		$label = $is_deposit
			? __('deposit of', 'dycore')
			: __('full payment of', 'dycore');
		$style_attr = ' style="padding: 10px 0; color: ' . esc_attr($this->color) . '; background-color: ' . esc_attr($this->background_color) . ';" ';

		$message .= '<p class="large">' . esc_html(sprintf(__('Please send us the %s %s to complete this payment.', 'dycore'), $label, $amount)) . '</p>';
		$message .= '<p class="large">' . esc_html(sprintf(__('When paying with %s you must make sure that you use the %s network.', 'dycore'), $asset_name, $network_name)) . '</p>';
		$message .= '<p class="large">' . esc_html(__('Our payment address is as follows:', 'dycore')) . '</p>';
		$message .= '<p class="large copyToClipboard pointer" ' . $style_attr . '><strong ' . $style_attr . '>' . esc_html($address) . '</strong> <span class="dashicons dashicons-clipboard"></span></p>';

		return $message;
	}

	public function validate(array $tx): bool
	{
		if (!parent::validate($tx)) return false;
		if (!isset($this->enabled_networks[secure_post('dy_network', '', 'sanitize_key')])) {
			dy_errors::add(__('Choose a supported payment network.', 'dycore'));
			return false;
		}
		return true;
	}

}
