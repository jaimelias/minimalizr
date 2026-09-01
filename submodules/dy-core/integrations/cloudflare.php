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
			return false;
			
		} else {
			if(strlen($token) > 2048) {

				dy_errors::add(__('Invalid Turnstile response: token length > 2048'));

				return false;
			}
		}

		$secret_key = get_option('dy_cf_turnstile_secret_key');

		if(empty($secret_key))
		{
			write_log('Turnstile: missing secret key');

			dy_errors::add(__('Turnstile is not configured'));

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
				'Turnstile validation error: '
				. $response->get_error_message()
			);

			dy_errors::add(__('Unable to validate Turnstile'));

			return false;
		}

		$status_code = (int) wp_remote_retrieve_response_code($response);
		$data = json_decode(
			wp_remote_retrieve_body($response),
			true
		);

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

			dy_errors::add(__('Invalid Turnstile'));

			return false;
		}

		$cache[$cache_key] = true;

		return true;
	}
}

if (!function_exists('cloudflare_ban_ip_address')) {
    function cloudflare_ban_ip_address($ban_message = '') {
        /*
         * Do not use write_log() here. Its current implementation removes
         * sensitive fields directly from $_POST and therefore mutates the
         * active request.
         */
        $log = static function($message) {
            if (!is_scalar($message)) {
                $message = wp_json_encode($message);
            }

            error_log('[DynamicPackages Cloudflare] ' . (string) $message);
        };

        $token      = trim((string) get_option('dy_cloudflare_api_token'));
        $account_id = trim((string) get_option('dy_cloudflare_account_id'));

        if ($token === '' || $account_id === '') {
            $log('Missing Cloudflare API token or account ID.');
            return false;
        }

        if (
            preg_match('/[\r\n]/', $token)
            || !preg_match('/^[a-f0-9]{32}$/i', $account_id)
        ) {
            $log('Invalid Cloudflare credentials.');
            return false;
        }

        $remote_ip = trim((string) wp_unslash(
            $_SERVER['REMOTE_ADDR'] ?? ''
        ));

        $connecting_ip = trim((string) wp_unslash(
            $_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''
        ));

        if (!filter_var($remote_ip, FILTER_VALIDATE_IP)) {
            $log('Invalid REMOTE_ADDR.');
            return false;
        }

        if (!filter_var($connecting_ip, FILTER_VALIDATE_IP)) {
            $log('Missing or invalid CF-Connecting-IP.');
            return false;
        }

        /*
         * CF-Connecting-IP is trustworthy only when the immediate peer is a
         * Cloudflare proxy. Otherwise a direct-origin request could forge the
         * header and cause an unrelated address to be blocked.
         */
        $cloudflare_ranges = (array) apply_filters(
            'dy_cloudflare_proxy_ranges',
            array(
                '173.245.48.0/20',
                '103.21.244.0/22',
                '103.22.200.0/22',
                '103.31.4.0/22',
                '141.101.64.0/18',
                '108.162.192.0/18',
                '190.93.240.0/20',
                '188.114.96.0/20',
                '197.234.240.0/22',
                '198.41.128.0/17',
                '162.158.0.0/15',
                '104.16.0.0/13',
                '104.24.0.0/14',
                '172.64.0.0/13',
                '131.0.72.0/22',
                '2400:cb00::/32',
                '2606:4700::/32',
                '2803:f800::/32',
                '2405:b500::/32',
                '2405:8100::/32',
                '2a06:98c0::/29',
                '2c0f:f248::/32',
            )
        );

        $ip_is_in_cidr = static function($ip, $cidr) {
            $parts = explode('/', (string) $cidr, 2);

            if (count($parts) !== 2) {
                return false;
            }

            $ip_binary      = inet_pton($ip);
            $network_binary = inet_pton($parts[0]);
            $prefix_length  = filter_var(
                $parts[1],
                FILTER_VALIDATE_INT
            );

            if (
                $ip_binary === false
                || $network_binary === false
                || strlen($ip_binary) !== strlen($network_binary)
                || $prefix_length === false
            ) {
                return false;
            }

            $address_bits = strlen($ip_binary) * 8;

            if ($prefix_length < 0 || $prefix_length > $address_bits) {
                return false;
            }

            $whole_bytes = intdiv($prefix_length, 8);
            $remaining_bits = $prefix_length % 8;

            if (
                $whole_bytes > 0
                && substr($ip_binary, 0, $whole_bytes)
                    !== substr($network_binary, 0, $whole_bytes)
            ) {
                return false;
            }

            if ($remaining_bits === 0) {
                return true;
            }

            $mask = (0xff << (8 - $remaining_bits)) & 0xff;

            return (
                (ord($ip_binary[$whole_bytes]) & $mask)
                === (ord($network_binary[$whole_bytes]) & $mask)
            );
        };

        $trusted_proxy = false;

        foreach ($cloudflare_ranges as $cloudflare_range) {
            if ($ip_is_in_cidr($remote_ip, $cloudflare_range)) {
                $trusted_proxy = true;
                break;
            }
        }

        if (!$trusted_proxy) {
            $log(
                'Refusing to trust CF-Connecting-IP because REMOTE_ADDR '
                . "is not a Cloudflare proxy: {$remote_ip}"
            );
            return false;
        }

        $packed_ip = inet_pton($connecting_ip);
        $ip = $packed_ip !== false
            ? inet_ntop($packed_ip)
            : false;

        if ($ip === false) {
            $log('Unable to normalize CF-Connecting-IP.');
            return false;
        }

        $public_ip = filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );

        if ($public_ip === false) {
            $log("Refusing to block non-public IP: {$ip}");
            return false;
        }

        if (is_user_logged_in() && current_user_can('manage_options')) {
            $log("Refusing to block an administrator IP: {$ip}");
            return false;
        }

        if (
            apply_filters(
                'dy_cloudflare_ban_ip_allowlisted',
                false,
                $ip
            )
        ) {
            $log("Refusing to block allowlisted IP: {$ip}");
            return false;
        }

        $cache_key = 'dy_cf_ban_' . hash(
            'sha256',
            $account_id . '|' . $ip
        );

        $cached_status = get_transient($cache_key);

        if ($cached_status === 'blocked') {
            return true;
        }

        if ($cached_status === 'pending') {
            return false;
        }

        set_transient($cache_key, 'pending', MINUTE_IN_SECONDS);

        $target = filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_IPV6
        ) !== false ? 'ip6' : 'ip';

        $ban_message = sanitize_text_field((string) $ban_message);
        $ban_message = substr($ban_message, 0, 400);

        $notes = sprintf(
            'DynamicPackages %s UTC: %s',
            gmdate('Y-m-d H:i:s'),
            $ban_message
        );

        $ban_url = sprintf(
            'https://api.cloudflare.com/client/v4/accounts/%s/firewall/access_rules/rules',
            rawurlencode($account_id)
        );

        $response = wp_remote_post(
            $ban_url,
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type'  => 'application/json',
                ),
                'body' => wp_json_encode(
                    array(
                        'mode' => 'block',
                        'configuration' => array(
                            'target' => $target,
                            'value'  => $ip,
                        ),
                        'notes' => $notes,
                    )
                ),
                'data_format' => 'body',
                'timeout'     => 5,
            )
        );

        if (is_wp_error($response)) {
            delete_transient($cache_key);
            $log('Cloudflare request failed: ' . $response->get_error_message());
            return false;
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        $body = json_decode(
            wp_remote_retrieve_body($response),
            true
        );

        $success = (
            $status_code >= 200
            && $status_code < 300
            && is_array($body)
            && !empty($body['success'])
            && !empty($body['result']['id'])
        );

        if (!$success) {
            delete_transient($cache_key);

            $log(array(
                'message' => 'Cloudflare rejected the IP block.',
                'status'  => $status_code,
                'errors'  => is_array($body)
                    ? ($body['errors'] ?? array())
                    : array(),
            ));

            return false;
        }

        set_transient($cache_key, 'blocked', DAY_IN_SECONDS);

        $log(array(
            'message' => 'IP blocked.',
            'ip'      => $ip,
            'rule_id' => $body['result']['id'],
            'reason'  => $ban_message,
        ));

        return true;
    }
}


if(!function_exists('cloudflare_html_to_pdf')) {

    // valid formats: letter, legal, tabloid, ledger, a0, a1, a2, a3, a4, a5, a6
    // headerTemplate and footerTemplate are separate mini HTML documents — they don't inherit CSS from your main invoice HTML.
    // printBackground renders bg colors/images — needed for logo headers, shaded tables
    // margin units: mm, px, in, cm


    function wrap_html_for_cloudflare ($value, $lang = 'en') {
        return <<<HTML
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
        <style>
            body { font-family: Inter, sans-serif; font-size: 10pt;}
            table, tr, td { vertical-align: top; }
            td { padding: 12pt 8pt; line-height: 1.25; }
        </style>
    </head>
    <body>
    $value
    </body>
    </html>
    HTML;
    }

    function cloudflare_html_to_pdf(
        $html, 
        $filename, 
        $pdfOptions = [
            'format'             => 'a4',
            'printBackground'    => true,          
            'margin'             => [
                'top'    => '20mm',
                'bottom' => '20mm',
                'left'   => '15mm',
                'right'  => '15mm',
            ],
            'displayHeaderFooter' => true,
            'headerTemplate'     => '', 
            'footerTemplate'     => '',
        ]
    ) {
        $pdf_api_token      = trim((string) get_option('dy_cloudflare_pdf_api_token'));
        $account_id = trim((string) get_option('dy_cloudflare_account_id'));

        if($pdf_api_token === '' || $account_id === '') {
            return null;
        }

        $upload_dir    = wp_upload_dir();
        $temp_path     = $upload_dir['basedir'];
        $temp_filename = '/temp_' . uniqid() . '.pdf';
        $pdf_path      = $temp_path . $temp_filename;





        $response = wp_remote_post(
            "https://api.cloudflare.com/client/v4/accounts/{$account_id}/browser-rendering/pdf",
            array(
                'timeout' => 30, // rendering takes longer than a typical WP request
                'headers' => array(
                    'Authorization' => 'Bearer ' . $pdf_api_token,
                    'Content-Type'  => 'application/json',
                ),
                'body' => wp_json_encode(array(
                    'html' => wrap_html_for_cloudflare($html),
                    'pdfOptions' => $pdfOptions,
                )),
            )
        );

        if ( is_wp_error( $response ) ) {

            $error_message = [
                'error' => $response->get_error_message()
            ];

            write_log($error_message);

            return null;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        if ( $code !== 200 ) {
            $error_message = [
                'error' => "Cloudflare PDF error ({$code}): {$body}"
            ];

            write_log($error_message);

            return null;
        }

        file_put_contents( $pdf_path, $body );

        return array( 'filename' => $filename, 'pathname' => $pdf_path );
    }

}


