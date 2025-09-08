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

    public function export_post_types($data)
    {
        $post_type = $data['post_type'];
        $post_types = get_post_types();
        global $polylang;

        $output = array(
            "data" => array(),
            "status" => 500,
            "message" => "Internal server error"
        );

        if (!in_array($post_type, $post_types, true)) {
            $output['message'] = "Invalid post type";
            $output['status'] = 400;
            return new WP_REST_Response($output, 400);
        }

        $default_language = (string) default_language();
        $languages = (array) get_languages();

        $filter_lang = (string) ( isset($_GET['lang']) &&  in_array(sanitize_text_field($_GET['lang']), $languages)) 
            ? sanitize_text_field($_GET['lang'])
            : default_language();

        $args = array(
            'post_type' => $post_type,
            'posts_per_page' => -1
        );

        if(isset($polylang))
        {
            $args['lang'] = [$filter_lang];
        }

        $query = new WP_Query($args);
        $services = array();


        if ($query->have_posts()) {
            while ($query->have_posts()) {

                $query->the_post();
                $post = get_post();
                $current_language = current_language($post->ID);
 
                $this_service = (object) array(
                    'TITLE' => $post->post_title,
                );

                if(isset($polylang))
                {
                    foreach ($languages as $language) {
                        $lang_post_id = pll_get_post($post->ID, $language);
                    
                        if ($language === $default_language || $lang_post_id > 0) {
                            $link_key = strtoupper("reservation_links_by_language[{$language}]");
                            $this_service->$link_key = get_permalink($lang_post_id);
                        }
                    }
                }
                else
                {
                    $this_service->links[$current_language] = get_permalink($post->ID);
                }

                $services[] = $this_service;
            }

            wp_reset_postdata();

        }

        $output = array(
            "data" => $services,
            "status" => 200,
            "message" => "Success"
        );

        $result = new WP_REST_Response($output, 200);

        $result->set_headers(array(
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ));

        return $result;
    }
}

?>
