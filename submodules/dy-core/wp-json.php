<?php

if (!defined('WPINC')) exit;


#[AllowDynamicProperties]
class Dynamic_Core_WP_JSON
{
    public function __construct()
    {
        add_action('rest_api_init', array(&$this, 'core_args'));
    }

    public function core_args()
    {
        register_rest_route('dy-core', 'args', array(
            'methods' => 'GET',
            'callback' => array(&$this, 'core_args_callback'),
            'permission_callback' => '__return_true'
        ));

        // Register the new endpoint for exporting post types
        register_rest_route('dy-core', 'export-post-type/(?P<post_type>[a-zA-Z0-9-_]+)', array(
            'methods' => 'GET',
            'callback' => array(&$this, 'export_post_types'),
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
}

?>
