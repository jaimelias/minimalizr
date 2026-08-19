<?php

if (!defined('WPINC')) exit;


#[AllowDynamicProperties]
class Dynamic_Core_WP_JSON
{
    public function __construct()
    {
        add_action('rest_api_init', array($this, 'core_args'));
    }

    public function core_args()
    {
        register_rest_route('dy-core', 'args', array(
            'methods' => 'GET',
            'callback' => array($this, 'core_args_callback'),
            'permission_callback' => '__return_true'
        ));

        register_rest_route(
            'dy-core',
            '/country-codes/(?P<country_code>[a-z]{2})',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'country_codes_cb'),
                'permission_callback' => '__return_true',
                'args'                => array(
                    'country_code' => array(
                        'required'          => true,
                        'validate_callback' => static function($value) {
                            return is_string($value)
                                && 1 === preg_match('/^[a-z]{2}$/', $value);
                        },
                    ),
                ),
            )
        );
    }

    public function core_args_callback($req)
    {
        $site_time = get_site_time();

        $args = apply_filters('dy_core_wp_json_args', array());
        
        $args['dy_nonce'] = wp_create_nonce('dy_nonce');

        foreach($site_time as $k => $v)
        {
            $args[$k] = $v;
        }

        $result = new WP_REST_Response($args, 200);

        $result->set_headers(array(
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ));

        return $result;
    }

    public function country_codes_cb($request)
    {
        $country_code = (string) $request['country_code'];
        $file_path = plugin_dir_path(__FILE__)
            . 'json/countries/'
            . $country_code
            . '.json';

        if (!is_readable($file_path)) {
            return new WP_Error(
                'dy_core_countries_not_found',
                'Country data was not found.',
                array('status' => 404)
            );
        }

        $countries = wp_json_file_decode(
            $file_path,
            array('associative' => true)
        );

        if (!is_array($countries)) {
            return new WP_Error(
                'dy_core_countries_invalid_json',
                'Country data could not be decoded.',
                array('status' => 500)
            );
        }

        $response = rest_ensure_response($countries);

        // Long browser cache + edge cache (Cloudflare respects s-maxage)
        $response->header(
            'Cache-Control',
            'public, max-age=86400, s-maxage=2592000, immutable'
        );

        // ETag based on file content — lets clients/CDN revalidate cheaply
        $etag = '"' . md5_file($file_path) . '"';
        $response->header('ETag', $etag);

        // Last-Modified based on file mtime
        $response->header(
            'Last-Modified',
            gmdate('D, d M Y H:i:s', filemtime($file_path)) . ' GMT'
        );

        // Prevent Cloudflare from skipping cache due to a stray Set-Cookie
        header_remove('Set-Cookie');

        return $response;
    }
}

?>
