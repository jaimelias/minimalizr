<?php

if (!defined('WPINC')) exit;


#[AllowDynamicProperties]
class Dynamic_Core_WP_JSON
{
    public function __construct()
    {
        add_action('rest_api_init', array(&$this, 'core_args'));

        remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
        add_filter('rest_pre_serve_request', [$this, 'send_cors_headers'], 10, 4);
    }

    public function core_args()
    {
        register_rest_route('dy-core', 'args', array(
            'methods' => 'GET',
            'callback' => array(&$this, 'core_args_callback'),
            'permission_callback' => '__return_true'
        ));
    }

    public function core_args_callback($req)
    {
        $site_time = get_site_time();

        $args = array(
            'dy_nonce' => wp_create_nonce('dy_nonce')
        );

        $whatsapp_number = apply_filters('dy_whatsapp_number', '');

        if(!empty($whatsapp_number))
        {
            $args['whatsapp_number'] = $whatsapp_number;
        }

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

    public function send_cors_headers($served, $result, $request, $server)
    {
        if ($request->get_route() === '/dy-core/args') {
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type');
            header('Vary: Origin', false);

            return $served;
        }

        return rest_send_cors_headers($served);
    }
}

?>
