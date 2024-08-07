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

        $args = array(
            'post_type' => $post_type,
            'posts_per_page' => -1
        );

        $query = new WP_Query($args);
        $posts = array();

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $posts[] = array(
                    'ID' => get_the_ID(),
                    'title' => get_the_title(),
                    'content' => html_to_plain_text(apply_filters('the_content', get_the_content())),
                    'excerpt' => get_the_excerpt(),
                    'date' => get_the_date(),
                    'modified' => get_the_modified_date(),
                    'author' => get_the_author(),
                    'status' => get_post_status(),
                    'type' => get_post_type(),
                    'link' => get_permalink(),
                );
            }
            wp_reset_postdata();
        }

        $output = array(
            "data" => $posts,
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
