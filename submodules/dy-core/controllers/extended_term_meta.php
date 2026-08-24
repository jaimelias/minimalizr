<?php

if ( !defined( 'WPINC' ) ) exit;

if(!function_exists('dy_get_value_term_meta')) {

    /**
     * Retrieves term metadata for a term-meta-backed field.
     *
     * Values are cached by term ID and meta key for the remainder of the
     * current request, avoiding repeated get_term_meta() calls when the same
     * field is rendered more than once.
     *
     * @param array<string, mixed> $args Field configuration containing
     *                                   "term_id" and "key".
     * @return mixed Stored term metadata, or an empty string when the term ID
     *               is invalid or the metadata does not exist.
     */
    function dy_get_value_term_meta($args) {

        static $cache = [];

        if(!array_key_exists('term_id', $args)) {
            write_log('Param "args" must contain a "term_id" in dy_get_value_term_meta.');
            return '';
        }

        $term_id = filter_var($args['term_id'], FILTER_VALIDATE_INT);

        if($term_id === false || $term_id < 1) {
            write_log('Property "term_id" must be a positive integer in dy_get_value_term_meta.');
            return '';
        }

        $key = $args['key'];
        $cache_key = $term_id . ':' . $key;

        if(array_key_exists($cache_key, $cache)) {
            return $cache[$cache_key];
        }

        return $cache[$cache_key] = get_term_meta($term_id, $key, true);
    }
}

if(!class_exists('dy_input_term_meta')) {

    /**
     * Term-meta-backed implementation of the input field renderer.
     *
     * Inherits the input rendering helpers from dy_input_abstract and
     * retrieves field values with get_term_meta().
     */
    class dy_input_term_meta extends dy_input_abstract {

        protected static function get_value($args) {

            return dy_get_value_term_meta($args);
        }
    }
}

if(!class_exists('dy_select_term_meta')) {

    /**
     * Term-meta-backed implementation of the select field renderer.
     *
     * Inherits custom() and min_max() from dy_select_abstract and retrieves
     * the selected value with get_term_meta().
     */
    class dy_select_term_meta extends dy_select_abstract {

        protected static function get_value($args) {

            return dy_get_value_term_meta($args);
        }
    }
}

if(!class_exists('dy_textarea_term_meta')) {

    /**
     * Term-meta-backed implementation of the textarea field renderer.
     *
     * Inherits text() from dy_textarea_abstract and retrieves its content
     * with get_term_meta().
     */
    class dy_textarea_term_meta extends dy_textarea_abstract {

        protected static function get_value($args) {

            return dy_get_value_term_meta($args);
        }
    }
}
