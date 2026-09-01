<?php

if ( !defined( 'WPINC' ) ) exit;

#[AllowDynamicProperties]
class Dynamic_Core_Error_Page {

    private static $cache = [];

    function __construct() {
        //the_content, wp_title, pre_get_document_title, the_title

        add_action('wp_head', array($this, 'meta_tags'), PHP_INT_MAX);
        add_filter('the_content', array($this, 'the_content'), PHP_INT_MAX);
		add_filter('pre_get_document_title', array($this, 'wp_title'), PHP_INT_MAX);
		add_filter('wp_title', array($this, 'wp_title'), PHP_INT_MAX);
		add_filter('the_title', array($this, 'the_title'), PHP_INT_MAX);
        add_filter('get_the_excerpt', array($this, 'get_the_excerpt'), PHP_INT_MAX);
    }

    public function has_errors() {
        
        global $dy_request_invalids;

        return isset($dy_request_invalids) && is_array($dy_request_invalids) && count($dy_request_invalids) > 0;
    }

	public function meta_tags()
	{
		global $dy_request_invalids;

		if($this->has_errors())
		{		
            echo '<meta name="robots" content="noindex, nofollow" />';
            return;
		}
	}

    public function the_content($content) {

        global $dy_request_invalids;

		if($this->has_errors())
		{
			return implode('', array_map(function($message) {
				return sprintf('<p class="minimal_alert strong">%s</p>', esc_html($message));
			}, $dy_request_invalids));
		}

        return $content;
    }

    public function wp_title($title) {

		global $dy_request_invalids;
		
		if($this->has_errors())
		{
			return __('Error', 'dynamicpackages');
		}

        return $title;
    }

    public function the_title($title) {

        global $dy_request_invalids;

		if($this->has_errors())
		{
			return __('Error', 'dynamicpackages');
		}

        return $title;
    }

    public function get_the_excerpt($excerpt) {

        global $dy_request_invalids;

		if($this->has_errors())
		{
			return '';
		}

        return $excerpt;
    }
}

?>