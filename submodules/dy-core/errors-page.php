<?php

if (!defined('WPINC')) exit;

#[AllowDynamicProperties]
class dy_errors {

    private static $errors = [];
    private static $http_status = 400;

    public function __construct() {

        add_action('wp_head', array($this, 'meta_tags'), PHP_INT_MAX); 
        add_filter('the_content', array($this, 'the_content'), PHP_INT_MAX);
        add_filter('pre_get_document_title', array($this, 'wp_title'), PHP_INT_MAX); 
        add_filter('wp_title', array($this, 'wp_title'), PHP_INT_MAX); 
        add_filter('the_title', array($this, 'the_title'), PHP_INT_MAX); 
        add_filter('get_the_excerpt', array($this, 'get_the_excerpt'), PHP_INT_MAX); 
        add_action('template_redirect', array($this, 'template_redirect'), PHP_INT_MAX);
    }

    public static function has_errors(): bool {

        return !empty(self::$errors);
    }

    public static function add($input, $http_status = 400): void {

        $messages = is_array($input)
            ? array_values($input)
            : [$input];

        $messages = array_values(array_filter(
            array_map(
                static fn($message) => is_string($message)
                    ? trim($message)
                    : '',
                $messages
            ),
            static fn($message) => $message !== ''
        ));

        if(empty($messages)) {
            return;
        }

        self::$errors = array_values(array_unique([
            ...self::$errors,
            ...$messages
        ]));

        if (
            is_int($http_status)
            && $http_status >= 400
            && get_status_header_desc($http_status) !== ''
            && $http_status > self::$http_status
        ) {
            self::$http_status = $http_status;
        }
        
    }

    public function meta_tags(): void {

        if(self::has_errors()) {
            echo '<meta name="robots" content="noindex, nofollow">';
        }
    }

    public function the_content($content) {

        if(!self::has_errors()) {
            return $content;
        }

        return implode('', array_map(
            static fn($message) => sprintf(
                '<p class="minimal_alert strong">%s</p>',
                esc_html($message)
            ),
            self::$errors
        ));
    }

    public function wp_title($title) {

        return self::has_errors()
            ? __('Error')
            : $title;
    }

    public function the_title($title) {

        return self::has_errors()
            ? __('Error')
            : $title;
    }

    public function get_the_excerpt($excerpt) {

        return self::has_errors()
            ? ''
            : $excerpt;
    }

    public function template_redirect(): void {

        if(!self::has_errors()) {
            return;
        }

        status_header(self::$http_status);
        nocache_headers();
    }
}

?>