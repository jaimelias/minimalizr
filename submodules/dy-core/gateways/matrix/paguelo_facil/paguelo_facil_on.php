<?php

if (!defined('WPINC')) exit;

class paguelo_facil_on extends Dy_Gateway
{
	private static array $cache = [];

	public function __construct(string $settings_parent = 'dy-core')
	{
		$this->register('paguelo_facil_on');
		add_filter('dy_debug_instructions', [$this, 'debug_instructions']);
	}
	private static ?int $txt_status = null;
	private array $gateway_response = [];
	public function init(): void
	{
		$this->order_status = 'paid';
		
		$this->short_name = __('Paguelo Facil', 'dycore');
		$this->name = __('Paguelo Facil On-site', 'dycore');
		$this->type = 'card-on-site';
		$this->brands = ['Mastercard', 'Visa'];
		$this->cards_accepted = implode_last($this->brands, __('or', 'dycore'));
		$this->cclw = (string) dy_get_option($this->id, '');
		$this->show = (int) dy_get_option($this->id . '_show', 0);
		$this->min = (float) dy_get_option($this->id . '_min', 0.0);
		$this->max = (float) dy_get_option($this->id . '_max', 0.0);

		$this->color = '#fff';
		$this->background_color = '#262626';
		$this->dummy_cc = '4321502106746398';

		$debug_email = dy_sanitize_email(
			(string) dy_get_option($this->id . '_debug_email', '')
		);

		$this->debug_email = is_email($debug_email)
			? $debug_email
			: dy_sanitize_email((string) dy_get_option('admin_email', ''));


		$this->debug_mode = $this->debug();

		$this->production_url = 'https://secure.paguelofacil.com/rest/ccprocessing/';
		$this->sandbox_url = 'https://sandbox.paguelofacil.com/rest/ccprocessing/';
		$this->endpoint = ($this->debug_mode === -1)
			? $this->production_url
			: $this->sandbox_url;

		$this->plugin_dir_url = plugin_dir_url(__DIR__);
		$this->website_name = get_bloginfo('name');
		$this->icon = '<span class="dashicons dashicons-cart"></span>';
	}

	public function is_active(): bool
	{
		$output = false;
		$cache_key = $this->id . '_is_active';
		
        if (array_key_exists($cache_key, self::$cache)) {
            return self::$cache[$cache_key];
        }

		if(!empty($this->cclw))
		{
			$output = true;
		}

        //store output in $cache
        self::$cache[$cache_key] = $output;

		return $output;
	}

	public function settings_init(): void
	{
		register_setting($this->id . '_settings', $this->id, 'sanitize_text_field');
		register_setting($this->id . '_settings', $this->id . '_min', 'floatval');
		register_setting($this->id . '_settings', $this->id . '_max', 'floatval');
		register_setting($this->id . '_settings', $this->id . '_show', 'intval');
		register_setting($this->id . '_settings', $this->id . '_debug_email', 'dy_sanitize_email');
		
		add_settings_section(
			$this->id . '_control_section', 
			__( 'General Settings', 'dycore' ), 
			'', 
			$this->id . '_settings'
		);		
		
		add_settings_section(
			$this->id . '_settings_section', 
			sprintf(__( '%s Settings', 'dycore' ), $this->name), 
			'', 
			$this->id . '_settings'
		);
				
		add_settings_field( 
			$this->id, 
			__( 'CCLW', 'dycore' ), 
			['dy_input_option', 'text'], 
			$this->id . '_settings', 
			$this->id . '_settings_section', 
			[
				'key' => $this->id
			]
		);

		add_settings_field( 
			$this->id . '_min', 
			__( 'Min. Amount', 'dycore' ), 
			['dy_input_option', 'price'], 
			$this->id . '_settings', 
			$this->id . '_control_section', 
			[
				'key' => $this->id . '_min',
				'append' => currency_symbol(),
			]
		);

		add_settings_field( 
			$this->id . '_max', 
			__( 'Max. Amount', 'dycore' ), 
			['dy_input_option', 'price']	, 
			$this->id . '_settings', 
			$this->id . '_control_section', 
			[
				'key' => $this->id . '_max',
				'append' => currency_symbol(),
			]
		);
		

		add_settings_field( 
			$this->id . '_show', 
			__( 'Show', 'dycore' ), 
			['dy_select_option', 'custom'], 
			$this->id . '_settings', 
			$this->id . '_control_section',
			[
				'key' => $this->id . '_show',
				'options' => [
					0 => __('Full Payments and Deposits', 'dycore'),
					1 => __('Only Deposits', 'dycore'),
				]
			]
		);
		
		add_settings_field( 
			$this->id . '_debug_email', 
			__( 'Debug Email', 'dycore' ), 
			['dy_input_option', 'email'],
			$this->id . '_settings', 
			$this->id . '_control_section', 
			[
				'key' => $this->id . '_debug_email'
			]
		);
	}

	public function add_settings_page(): void
	{
		add_submenu_page( (string) apply_filters('dy_gateway_settings_parent', 'dy-core'), $this->name, '💸 '. $this->short_name, 'manage_options', $this->id, [$this, 'settings_page']);
	}

	public function settings_page(): void
		 { 
		?><div class="wrap">
		<form action="options.php" method="post">
			
			<h1><?php esc_html_e($this->name); ?></h1>	
			<?php echo $this->debug_instructions(); ?>
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
		$output .= '<p class="large text-muted">'.sprintf(__('Pay with %s thanks to %s', 'dycore'), $this->cards_accepted, $this->short_name).'</p>';
		return $output;
	}

	public  function validate_card(): bool
	{
		$invalids = [];
		$output = false;
		$cache_key = 'dy_validate_card';
		
        if (array_key_exists($cache_key, self::$cache)) {
            return self::$cache[$cache_key];
        }

		if(!luhn_check(secure_post('CCNum')))
		{
			$invalids[] = __('Invalid Credit Card. Please return to the previous page to correct the numbers.', 'dycore');
		}

		$ExpMonth = secure_post('ExpMonth', 0, 'absint');
		$ExpYear  = secure_post('ExpYear', 0, 'absint');

		$current_year  = (int) wp_date('Y');
		$current_month = (int) wp_date('n');

		$month_is_valid = (
			$ExpMonth >= 1
			&& $ExpMonth <= 12
		);

		$year_is_valid = (
			$ExpYear >= $current_year
			&& $ExpYear <= $current_year + 20
		);

		if(!$month_is_valid)
		{
			$invalids[] = __('Invalid expiration month.', 'dycore');
		}

		if(!$year_is_valid)
		{
			$invalids[] = __('Invalid expiration year.', 'dycore');
		}
		else if(
			$ExpYear === $current_year
			&& $month_is_valid
			&& $ExpMonth < $current_month
		)
		{
			$invalids[] = __('The card has expired.', 'dycore');
		}

		$CVV2     = secure_post('CVV2');
		
		if(!is_string($CVV2) || preg_match('/\A\d{3}\z/', $CVV2) !== 1) {
			$invalids[] = __('Invalid CVV (security code on the back of the card).', 'dycore');
		}

		$country = secure_post('country');
		
		if(empty($country) || !is_string($country) || strlen($country) !== 2)
		{
			$invalids[] = __('Invalid country.', 'dycore');
		}
		if(empty(secure_post('city')))
		{
			$invalids[] = __('Invalid city.', 'dycore');
		}
		if(empty(secure_post('address')))
		{
			$invalids[] = __('Invalid address.', 'dycore');
		}
		
		if(count($invalids) === 0)
		{
			$output = true;			
		}
		else
		{
			dy_errors::add($invalids);
		}

		//store output in $cache        

		return self::$cache[$cache_key] = $output;
	}

	public function process_transaction(array $tx): array
	{
		$this->transaction = $tx;
		self::$txt_status = $this->resolve_checkout_status();
		$tx['status'] = match (self::$txt_status) {
			2 => 'success',
			1 => 'declined',
			default => 'error',
		};
		$tx['gateway'] = $this->gateway_result();
		if (isset($this->error_codes)) {
			write_log($this->error_codes);
		}

		return $tx;
	}

	private function resolve_checkout_status(): int
	{
		$this->gateway_response = [];

		if($this->debug_mode >= 0 && $this->debug_mode !== 3)
		{
			return $this->debug_mode;
		}

		$response = $this->process_request();

		if(!is_array($response))
		{
			$this->error_codes = array(
				'error' => 'invalid_response_format'
			);

			return 0;
		}

		$this->gateway_response = $response;

		if(array_key_exists('error', $response))
		{
			$this->error_codes = array(
				'error' => (string) $response['error']
			);

			return 0;
		}

		$status = isset($response['Status'])
			? (string) $response['Status']
			: '';

		if($status === 'Approved')
		{
			return 2;
		}

		if($status === 'Declined')
		{
			$this->error_codes = array(
				'RespText' => (string) ($response['RespText'] ?? ''),
				'RespCode' => (string) ($response['RespCode'] ?? '')
			);

			return 1;
		}

		$this->error_codes = array(
			'error' => 'unexpected_gateway_status'
		);

		return 0;
	}

	private function gateway_result(): array
	{
		$tx = $this->transaction;
		$status = match (self::$txt_status) {
			2 => 'approved',
			1 => 'declined',
			default => 'error',
		};
		$response = [];
		$code = $this->gateway_response_value(['RespCode', 'resp_code', 'code']);
		$text = $this->gateway_response_value(['RespText', 'resp_text', 'message']);
		$reference = $this->gateway_response_value([
			'Reference',
			'reference',
			'RRN',
			'CodOper',
			'TransactionId',
			'TransactionID',
			'AuthCode',
		]);

		if ($code !== '') {
			$response['code'] = $code;
		}
		if ($text !== '') {
			$response['text'] = $text;
		}

		if ($response === [] && isset($this->error_codes) && is_array($this->error_codes)) {
			$error_code = $this->error_codes['RespCode'] ?? $this->error_codes['error'] ?? '';
			$error_text = $this->error_codes['RespText'] ?? '';

			if (is_scalar($error_code) && preg_match('/\A[a-zA-Z0-9_-]{1,64}\z/', (string) $error_code) === 1) {
				$response['code'] = sanitize_key((string) $error_code);
			}
			if (is_scalar($error_text) && (string) $error_text !== '') {
				$response['text'] = sanitize_text_field((string) $error_text);
			}

			if ($response === []) {
				$response['code'] = 'gateway_request_failed';
			}
		}

		$payment_amount = round((float) $this->amount($tx), 2);

		return [
			'id' => $this->id,
			'status' => $status,
			'reference' => $reference,
			'response' => $response,
			'metadata' => [
				'environment' => $this->debug_mode === -1 ? 'production' : 'sandbox',
				'payment_amount' => $payment_amount,
				'formatted_payment_amount' => (string) dy_tx::service_value('formatted_charge_amount', '', $tx),
				'currency' => (string) dy_tx::service_value('currency', '', $tx),
				'payment_type' => (string) dy_tx::service_value('payment_type', 'payment', $tx),
			],
		];
	}

	private function gateway_response_value(array $keys): string
	{
		foreach ($keys as $key) {
			$value = $this->gateway_response[$key] ?? null;

			if (is_scalar($value) && trim((string) $value) !== '') {
				return sanitize_text_field((string) $value);
			}
		}

		return '';
	}

	public function confirmation_result(array $result, array $tx): array
	{
		$result = is_array($result) ? $result : [];

		$gateway = dy_tx::gateway($tx);

		if (($gateway['id'] ?? '') !== $this->id) {
			return $result;
		}

		$metadata = is_array($gateway['metadata'] ?? null)
			? $gateway['metadata']
			: [];
		$status = sanitize_key((string) ($gateway['status'] ?? 'error'));
		$amount = max(0, (float) ($metadata['payment_amount'] ?? 0));
		$formatted_amount = (string) (
			$metadata['formatted_payment_amount']
			?? dy_tx::service_value('formatted_payment_amount', '', $tx)
		);
		$payment_type = ($metadata['payment_type'] ?? '') === 'deposit'
			? 'deposit'
			: 'payment';
		$description = (string) dy_tx::service_value(
			'description',
			(string) ($result['excerpt'] ?? ''),
			$tx
		);

		$result['excerpt'] = $description;

		if ($status === 'approved') {
			$result['title'] = __('Payment Approved', 'dycore');
			$result['content'] = '<p class="minimal_success strong">' . esc_html(sprintf(__('Thank you for your payment of %s.', 'dycore'), $formatted_amount)) . '</p>';

			return $result;
		}

		if ($status === 'declined') {
			$result['title'] = __('Payment Declined', 'dycore');
			$result['content'] = '<p class="minimal_alert strong">'
				. esc_html(__('Please contact your bank to authorize the transaction.', 'dycore'))
				. '</p>'
				. $this->gateway_response_html($gateway);

			return $result;
		}

		$result['title'] = __('Checkout Error', 'dycore');
		$result['content'] = '<p class="minimal_alert strong">'
			. esc_html(__('Please try again in a few minutes. Our staff will be in touch with you very soon.', 'dycore'))
			. '</p>'
			. $this->gateway_response_html($gateway);

		return $result;
	}

	private function gateway_response_html(array $gateway): string
	{
		$response = is_array($gateway['response'] ?? null)
			? $gateway['response']
			: [];
		$output = '';

		foreach (['text' => 'RespText', 'code' => 'RespCode'] as $key => $label) {
			$value = $response[$key] ?? '';

			if (!is_scalar($value) || (string) $value === '') {
				continue;
			}

			$output .= '<p class="minimal_alert strong">'
				. esc_html($label . ': ' . (string) $value)
				. '</p>';
		}

		return $output;
	}

	public function build_request(): array
	{
		$tx = $this->transaction;
		$ip = get_ip_address();

		// Datos principales
		$CCNum  = secure_post('CCNum');
		$CVV2   = secure_post('CVV2');
		$email  = dy_tx::payload_value('email', '', $tx);
		$phone  = dy_tx::payload_value('country_calling_code', '', $tx) . dy_tx::payload_value('phone', '', $tx);

		$ExpYear = secure_post('ExpYear', 0, 'absint');
		$ExpYear = sprintf('%02d', $ExpYear % 100);

		$ExpMonth = secure_post('ExpMonth', 0, 'absint');
		$ExpMonth = sprintf('%02d', $ExpMonth);

		// Hash secreto
		$hash = $CCNum . $CVV2 . $email;

		// Armar payload
		return [
			'CCLW'       => $this->cclw,
			'txType'     => 'SALE',
			'CMTN'       => round($this->amount($tx), 2),
			'CDSC'       => substr(dy_tx::service_value('description', '', $tx), 0, 150),
			'CCNum'      => $CCNum,
			'ExpMonth'   => $ExpMonth,
			'ExpYear'    => $ExpYear,
			'CVV2'       => $CVV2,
			'Name'       => dy_tx::payload_value('first_name', '', $tx),
			'LastName'   => dy_tx::payload_value('lastname', '', $tx),
			'Email'      => $email,
			'Address'    => secure_post('address'),
			'Tel'        => $phone,
			'Ip'         => $ip,
			'SecretHash' => hash('sha512', $hash),
		];
	}

	public function process_request(): mixed
	{
		$params = http_build_query($this->build_request());
		
		$ch = curl_init();
		curl_setopt($ch,CURLOPT_URL, $this->endpoint);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_AUTOREFERER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true );
		curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded', 'Accept: */*'));
		curl_setopt($ch,CURLOPT_POSTFIELDS, $params);
		curl_setopt($ch, CURLOPT_TIMEOUT, 120);
		$result = curl_exec($ch);

		if($result === false) {
			$curl_error = curl_error($ch);
			curl_close($ch);
			return array("error" => 'curl_error: ' . $curl_error);
		}

		$decoded_result = json_decode($result, true);
		curl_close($ch);

		return $decoded_result;
	}

	public function debug(): int
	{
		if(
			post_has('CCNum')
			&& post_has('CVV2')
			&& post_has('email')
			&& secure_post('CCNum') === $this->dummy_cc
			&& $this->user_can_debug()
			&& dy_tx::request_value('email') === $this->debug_email
		)
		{
			if(secure_post('CVV2') === '222')
			{
				return 2;
			}

			if(secure_post('CVV2') === '111')
			{
				return 1;
			}

			if(secure_post('CVV2') === '000')
			{
				return 0;
			}

			return 3;
		}

		return -1;
	}

	public function user_can_debug(): bool
	{
		$output = false;
		
		if(is_user_logged_in())
		{
			if(current_user_can('editor') || current_user_can('administrator'))
			{
				$output = true;
			}
		}
		
		return $output;
	}

	public function debug_instructions(): ?string
	{
		if($this->user_can_debug())
		{
			return '<p style="line-height: 2; color: #696969; background-color: #ADD8E6; padding: 10px;">🤖 '.sprintf(__('Use the card %s together with the email %s to test Paguelo Facil Development Enviroment. Use the CVV code 222 to generate approved transactions, 111 to generate declined transaction and 000 to generate errors and any other number will retreive Paguelo Facil original response.', 'dycore'), '<strong>'.esc_html($this->dummy_cc).'</strong>', '<strong>'.esc_html($this->debug_email).'</strong>').'</p>';			
		}
		return null;
	}

	public function validate(array $tx): bool
	{
		return parent::validate($tx) && $this->validate_card();
	}

	public function available(array $service): bool
	{
		return $this->min > 0 && $this->max >= $this->min && parent::available($service);
	}

}
