<?php

if ( !defined( 'WPINC' )) exit;

if(!function_exists('cloudflare_ban_ip_address')) {
    function cloudflare_ban_ip_address($ban_message = '') {
        $dy_cf_log = static function($message) {
            if (function_exists('write_log')) {
                write_log($message);
                return;
            }

            error_log(is_scalar($message) ? (string) $message : wp_json_encode($message));
        };

        $token      = get_option('dy_cloudflare_api_token');
        $account_id = get_option('dy_cloudflare_account_id');
        if (empty($token) || empty($account_id)) {
            $dy_cf_log('Cloudflare: missing API token or account_id');
            return false;
        }

        $ip = function_exists('get_ip_address') ? get_ip_address() : ($_SERVER['REMOTE_ADDR'] ?? '');
        $ip = trim((string) $ip);

        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            $dy_cf_log('Cloudflare: invalid IP detected: ' . $ip);
            return false;
        }

        $cache_key = 'dy_cf_ban_attempt_' . md5($ip);
        if (function_exists('get_transient') && get_transient($cache_key)) {
            $dy_cf_log("Cloudflare: recently attempted ban for {$ip}, skipping duplicate");
            return false;
        }

        if (function_exists('set_transient')) {
            $cache_ttl = defined('HOUR_IN_SECONDS') ? HOUR_IN_SECONDS : 3600;
            set_transient($cache_key, 1, $cache_ttl);
        }

        $query_key = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? 'ipv6' : 'ipv4';

        // 1) Look up IP Intel
        $intel_url = sprintf(
            'https://api.cloudflare.com/client/v4/accounts/%s/intel/ip?%s=%s',
            rawurlencode($account_id),
            $query_key,
            rawurlencode($ip)
        );

        $auth_header = array(
            'Authorization' => 'Bearer ' . sanitize_text_field($token),
        );

        $intel_resp = wp_remote_get($intel_url, array('headers' => $auth_header, 'timeout' => 12));

        if (is_wp_error($intel_resp)) {
            $dy_cf_log('Cloudflare Intel error: ' . $intel_resp->get_error_message());
            return false; // fail safe: don’t ban if we can’t classify
        }

        $intel_code = (int) wp_remote_retrieve_response_code($intel_resp);
        $intel_body = json_decode(wp_remote_retrieve_body($intel_resp), true);
        if (empty($intel_body['success']) || empty($intel_body['result'][0])) {
            $dy_cf_log("Cloudflare Intel Error {$intel_code}: " . wp_json_encode($intel_body));
            return false;
        }

        $ip_info = $intel_body['result'][0];

        // pull useful bits (defensively)
        $asn         = (string)($ip_info['asn']['asn'] ?? '');
        $asn_name    = strtoupper((string)($ip_info['asn']['name'] ?? ''));
        $tags        = array_map('strtoupper', (array)($ip_info['tags'] ?? []));
        $threats     = array_map('strtoupper', (array)($ip_info['threat_categories'] ?? []));
        $risk_score  = (int)($ip_info['risk_score'] ?? 0);
        $infra_type  = strtoupper((string)($ip_info['infrastructure_type'] ?? ''));

        // 2) Decide: allowlist well-known crawlers / safe infra
        $is_google   = ($asn === '15169' || strpos($asn_name, 'GOOGLE') !== false);
        $is_msft     = ($asn === '8075'  || strpos($asn_name, 'MICROSOFT') !== false);
        $is_bing     = $is_msft; // Bing typically on Microsoft ASNs
        $is_known_se = $is_google || $is_bing || in_array('SEARCH ENGINE', $tags, true);

        $has_threats = !empty($threats);
        $is_risky    = ($risk_score >= 75)
            || $has_threats
            || in_array('ANONYMIZER', $tags, true)
            || in_array('ANONYMIZER', $threats, true);

        // Optional: if you want to be extra sure for Google, also do reverse-DNS here (per Google docs).

        if ($is_known_se && !$is_risky) {
            // Don’t ban good crawlers
            $dy_cf_log("Skip ban: {$ip} identified as search engine ({$asn_name}), risk={$risk_score}");
            return false;
        }

        // 3) Proceed to ban via Access Rules (account-level)
        $ban_url = sprintf('https://api.cloudflare.com/client/v4/accounts/%s/firewall/access_rules/rules', rawurlencode($account_id));
        $note_message = is_array($ban_message) ? implode(', ', array_map('strval', $ban_message)) : (string)$ban_message;
        $note = 'Banned ' . gmdate('Y-m-d H:i:s') . ' UTC | ' . $note_message;
        $note = substr(sanitize_text_field($note), 0, 500);

        $data = array(
            'mode'          => 'block',
            'configuration' => array('target' => 'ip', 'value' => $ip),
            'notes'         => $note,
        );

        $ban_resp = wp_remote_post($ban_url, array(
            'headers'     => array_merge($auth_header, array('Content-Type' => 'application/json')),
            'body'        => wp_json_encode($data),
            'data_format' => 'body',
            'timeout'     => 15,
        ));

        if (is_wp_error($ban_resp)) {
            $dy_cf_log('Cloudflare ban error: ' . $ban_resp->get_error_message());
            return false;
        }

        $code    = (int) wp_remote_retrieve_response_code($ban_resp);
        $decoded = json_decode(wp_remote_retrieve_body($ban_resp), true);

        if ($code >= 200 && $code < 300 && !empty($decoded['success'])) {
            $dy_cf_log('Cloudflare WAF Banned IP: ' . wp_json_encode(array(
                'ip' => $ip,
                'asn' => $asn,
                'asn_name' => $asn_name,
                'risk_score' => $risk_score,
                'threats' => $threats,
            )));
            return true;
        } else {
            $dy_cf_log("Cloudflare WAF Error {$code}: " . wp_json_encode($decoded));
            return false;
        }
    }


}