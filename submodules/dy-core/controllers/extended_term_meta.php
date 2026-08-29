<?php

if ( !defined( 'WPINC' ) ) exit;

if(!function_exists('dy_taxonomy_form_row')) {

    /**
     * Builds the opening and closing markup for a WordPress taxonomy form row.
     *
     * The returned object is intended for use with the `prepend` and `append`
     * arguments supported by term-meta field renderers.
     *
     * The `prepend` property contains the opening `<tr>`, `<th>`, field label,
     * and opening `<td>`. The `append` property contains the optional field
     * description and the closing `</td>` and `</tr>` tags.
     *
     * The key, label, and description are escaped before being added to the
     * generated markup. This function returns markup but does not print it.
     *
     * Do not also pass `label` to the field renderer because this helper already
     * generates the field label.
     *
     * Example:
     *
     *     $row = dy_taxonomy_form_row(
     *         'tax_add_ons_max',
     *         __('Maximum Number of participants', 'dynamicpackages')
     *     );
     *
     *     dy_select_term_meta::min_max([
     *         'term_id' => $term_id,
     *         'key'     => 'tax_add_ons_max',
     *         'min'     => 1,
     *         'max'     => 500,
     *         'step'    => 1,
     *         'prepend' => $row->prepend,
     *         'append'  => $row->append,
     *     ]);
     *
     * @param string      $key         Field ID used by the label's `for` attribute.
     * @param string      $label       Visible field label. Plain text only.
     * @param string|null $description Optional field description. Plain text only.
     *
     * @return object{prepend: string, append: string} Object containing the HTML
     *                                                  fragments used to wrap the field.
     */

    function dy_taxonomy_form_row(
        $key,
        $label,
        $description = null
    ) {
        $description_html = '';

        if(is_string($description) && trim($description) !== '') {
            $description_html = sprintf(
                '<br/><p class="description">%s</p>',
                esc_html($description)
            );
        }

        return (object) [
            'prepend' => sprintf(
                '<tr class="form-field">
                    <th scope="row" valign="top">
                        <label for="%s">%s</label>
                    </th>
                    <td>',
                esc_attr($key),
                esc_html($label)
            ),
            'append' => $description_html . '</td></tr>',
        ];
    }
}

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
