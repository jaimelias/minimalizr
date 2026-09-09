<?php

if (!defined('WPINC')) exit;

if(!function_exists('validate_turnstile')) {
	
	function validate_turnstile()
	{
		static $cache = [];
		$cache_key = 'dy_valid_turnstile';

		/*
		* Several classes validate the same request.
		* Turnstile tokens are single-use, so Siteverify must only be
		* called once during the current WordPress request.
		*/
		if(array_key_exists($cache_key, $cache))
		{
			return (bool) $cache[$cache_key];
		}

		$cache[$cache_key] = false;

		/*
		* Compatibility mode keeps the reCAPTCHA field name.
		* Change this to cf-turnstile-response only during Phase 2.
		*/
		$token = secure_post('cf-turnstile-response');

		if(empty($token))
		{

            dy_errors::add(
                __('The Turnstile response is missing.', 'dynamicpackages')
            );

			return false;
			
		} else {
			if(strlen($token) > 2048) {

				dy_errors::add(__('Invalid Turnstile response: token length > 2048.'));

				return false;
			}
		}

		$secret_key = get_option('dy_cf_turnstile_secret_key');

		if(empty($secret_key))
		{
			write_log('Turnstile: missing secret key');

			dy_errors::add(__('Turnstile is not configured.'), 500);

			return false;
		}

		$response = wp_remote_post(
			'https://challenges.cloudflare.com/turnstile/v0/siteverify',
			array(
				'timeout' => 10,
				'body' => array(
					'secret'   => $secret_key,
					'response' => $token,
					'remoteip' => get_ip_address(),
				),
			)
		);

		if(is_wp_error($response))
		{
            
            write_log(
                [
                    'message' => 'Turnstile validation request failed.',
                    'error'   => $response->get_error_message()
                ],
                false,
                false,
                'ERROR'
            );

			dy_errors::add(__('Unable to validate Turnstile.'), 502);

			return false;
		}

		$status_code = (int) wp_remote_retrieve_response_code($response);

        $decoded = wp_remote_retrieve_body($response);

        if(!is_safe_json($decoded)) {
            write_log(__('Invalid json string in validate_turnstile.'));
            dy_errors::add(__('Unable to validate Turnstile.'), 502);
            return false;
        }
        
		$data = json_decode($decoded, true);

		$expected_hostname = (string) wp_parse_url(
			home_url(),
			PHP_URL_HOST
		);

		$valid = (
			$status_code === 200
			&& is_array($data)
			&& !empty($data['success'])
			&& isset($data['hostname'])
			&& in_array($data['hostname'], [$expected_hostname, 'example.com'])
		);

		if(!$valid)
		{
			$errors = (
				is_array($data)
				&& isset($data['error-codes'])
				&& is_array($data['error-codes'])
			)
				? $data['error-codes']
				: array();

			write_log(array(
				'message' => 'Turnstile validation failed',
				'status_code' => $status_code,
				'error_codes' => $errors,
				'action' => $data['action'] ?? null,
				'hostname' => $data['hostname'] ?? null,
			));

            $http_status = (
                $status_code !== 200
                || !is_array($data)
            ) ? 502 : 400;

			dy_errors::add(
                __('Turnstile validation failed.'), 
                $http_status
            );

			return false;
		}

		$cache[$cache_key] = true;

		return true;
	}
}

?>