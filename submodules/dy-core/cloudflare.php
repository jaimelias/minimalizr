<?php

if (!defined('WPINC')) exit;

if (!function_exists('cloudflare_ban_ip_address')) {
    function cloudflare_ban_ip_address($ban_message = '') {
        $token      = get_option('dy_cloudflare_api_token');
        $account_id = get_option('dy_cloudflare_account_id');

        if (empty($token) || empty($account_id)) {
            write_log('Cloudflare: missing API token or account_id');
            return false;
        }

        $ip = function_exists('get_ip_address')
            ? get_ip_address()
            : ($_SERVER['REMOTE_ADDR'] ?? '');

        $ip = trim((string) $ip);

        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            write_log('Cloudflare: invalid IP detected: ' . $ip);
            return false;
        }

        $ban_message = sanitize_text_field((string) $ban_message);

        $ban_url = sprintf(
            'https://api.cloudflare.com/client/v4/accounts/%s/firewall/access_rules/rules',
            rawurlencode($account_id)
        );

        $data = array(
            'mode' => 'block',
            'configuration' => array(
                'target' => 'ip',
                'value'  => $ip,
            ),
            'notes' => $ban_message,
        );

        $response = wp_remote_post($ban_url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . sanitize_text_field($token),
                'Content-Type'  => 'application/json',
            ),
            'body'        => wp_json_encode($data),
            'data_format' => 'body',
            'timeout'     => 15,
        ));

        if (is_wp_error($response)) {
            write_log('Cloudflare ban error: ' . $response->get_error_message());
            return false;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code >= 200 && $code < 300 && !empty($body['success'])) {
            write_log("Cloudflare banned IP {$ip}: {$ban_message}");
            return true;
        }

        write_log("Cloudflare ban error {$code}: " . wp_json_encode($body));
        return false;
    }
}