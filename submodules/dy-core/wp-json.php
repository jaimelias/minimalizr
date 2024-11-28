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

        $args = array(
            'post_type' => $post_type,
            'posts_per_page' => -1
        );

        $query = new WP_Query($args);
        $posts = array();
        $default_language = default_language();
        $languages = get_languages();

        if ($query->have_posts()) {
            while ($query->have_posts()) {

                $query->the_post();
                $post = get_post();

                $current_language = current_language($post->post_name);

                $this_post = array(
                    'ID' => $post->ID,
                    'title' => $post->post_title,
                    'post_content' => html_to_plain_text(apply_filters('the_content', $post->post_content)),
                    'post_excerpt' => $post->post_excerpt,
                    'date' => $post->post_date,
                    'modified' => $post->post_modified,
                    'author' => $post->post_author,
                    'status' => $post->post_status,
                    'type' => $post->post_type,
                    'current_language' => $current_language,
                    'post_parent' => $post->post_parent,
                    'links' => array(),
                    'exclude' => false
                );

                if(isset($polylang))
                {
                    foreach ($languages as $language) {
                        $lang_post_id = pll_get_post($post->ID, $language);
                    
                        if ($language === $default_language || $lang_post_id > 0) {
                            $this_post['links'][$language] = get_permalink($lang_post_id);
                        }
                    }
                }
                else
                {
                    $this_post['links'][$current_language] = get_permalink($post->ID);
                }

                $parsed_post = apply_filters('dy_export_post_types', $this_post);

                if(!array_key_exists('exclude', $parsed_post) || $parsed_post['exclude'] === false)
                {
                    unset($this_post['exclude']);
                    $posts[] = apply_filters('dy_export_post_types', $this_post);
                }
                
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
