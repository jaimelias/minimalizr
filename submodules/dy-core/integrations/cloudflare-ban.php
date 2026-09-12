<?php

if ( ! defined( 'WPINC' ) ) {
    exit;
}

if (!function_exists('cloudflare_ban_ip_address')) {
    function cloudflare_ban_ip_address( mixed $ban_message = '' ): bool {
        $log = static function(
            mixed $details,
            string $level = 'ERROR'
        ): void
        {
            if ( ! is_array( $details ) ) {
                $details = [ 'message' => (string) $details ];
            }

            write_log(
                [
                    'component' => 'cloudflare',
                    ...$details,
                ],
                false,
                false,
                $level
            );
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

        $remote_ip     = secure_server('REMOTE_ADDR');
        $connecting_ip = secure_server('HTTP_CF_CONNECTING_IP');

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
        $is_cloudflare_proxied = is_cloudflare_proxied();

        if (!$is_cloudflare_proxied) {
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

        if (filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false) {
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
                    ? ($body['errors'] ?? [])
                    : [],
            ));

            return false;
        }

        set_transient($cache_key, 'blocked', DAY_IN_SECONDS);

        $log(
            [
                'message' => 'IP blocked.',
                'ip'      => $ip,
                'rule_id' => $body['result']['id'],
                'reason'  => $ban_message
            ],
            'INFO'
        );

        return true;
    }
}
