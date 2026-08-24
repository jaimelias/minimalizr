<?php

/**
 * dy_select_abstract
 *
 * Renders self-populating <select> dropdown fields whose selected value is
 * read from a WordPress option via the "key" passed in $args. Two entry
 * points are provided: custom(), which takes an explicit value => label
 * "options" array, and min_max(), a convenience wrapper that generates a
 * numeric range of options and delegates to custom().
 *
 * Common $args keys (custom()):
 * - key      (string, required) Option name used to fetch/cache the
 *             selected value and used as the "name"/"id" attribute.
 * - options  (array, required) Associative array of value => label pairs
 *             rendered as <option> elements.
 * - post_id  (int|null, optional) Present to signal a post-meta context;
 *             see get_value() for how this should interact with storage.
 * - klass    (string, optional) Alias for "class"; converted internally
 *             so calling code can pass a CSS class without colliding with
 *             the "class" key used internally on the settings page.
 * - append   (string, optional) Raw HTML appended after the </select> tag
 *             (passed through wp_kses_post).
 * - prepend  (string, optional) Raw HTML prepended before the <select> tag
 *             (passed through wp_kses_post).
 * - Any other key (class, multiple, disabled, etc.) is rendered directly
 *   as an HTML attribute on the <select> element.
 *
 * min_max() additionally requires:
 * - min   (int, required) Lower bound of the generated range (inclusive).
 * - max   (int, required) Upper bound of the generated range (inclusive).
 * - step  (int, required) Positive integer increment between options.
 *
 * Usage:
 *
 *     dy_select_abstract::custom([
 *         'key' => 'status',
 *         'options' => [
 *             0 => 'Active',
 *             1 => 'Pending',
 *         ],
 *     ]);
 *
 *     dy_select_abstract::min_max([
 *         'key'  => 'status',
 *         'min'  => 0,
 *         'max'  => 100,
 *         'step' => 1,
 *     ]);
 *
 * @package dy
 */

if ( !defined( 'WPINC' ) ) exit;

if(!class_exists('dy_select_abstract')) {

	abstract class dy_select_abstract {

        /* 

        dy_select_abstract::custom([
            'key' => 'status',
            'options' => [
                0 => 'Active',
                1 => 'Pending',
            ],
        ]);

        dy_select_abstract::min_max([
            'key'  => 'status',
            'min'  => 0,
            'max'  => 100,
            'step' => 1,
        ]);

        */

		abstract protected static function get_value($args);

        public static function custom($args = []) {

            if(!is_array($args)) {
                write_log('Param "args" must be an array in "dy_select_abstract".');
                return '';
            }

			if(!array_key_exists('key', $args)) {
				write_log('Param "args" must contain a "key" property in "dy_select_abstract".');
				return '';
			}

            $key = $args['key'];

            if(!is_string($key) || trim($key) === '') {
                write_log('Property "key" must be a non-empty string in "dy_select_abstract".');
                return '';
            }

            if(!array_key_exists('options', $args)) {
                write_log('Param "args" must contain an "options" property in "dy_select_abstract".');
                return '';
            }

            $options = $args['options'];

            if(!is_array($options)) {
                write_log('Param "args"["options"] must be an array in "dy_select_abstract".');
                return '';
            }

			$append = $args['append'] ?? '';
			$prepend = $args['prepend'] ?? '';
			
            //replaces the current class by the attribute of class
            if(array_key_exists('klass', $args)) {
                if(is_string($args['klass']) && trim($args['klass']) !== '') {
                    $args['class'] = $args['klass'];
                }

                unset($args['klass']);
            }

            $stored_value =  static::get_value($args);

            $args = array_merge(
                $args,
                [
                    'name' => $key,
                    'id'   => $key,
                ]
            );

            $attributes = [];

            foreach($args as $attribute => $attribute_value) {

				$is_prefixed_attribute = is_string($attribute)
					&& preg_match(
						'/^(?:data|aria)-[a-z][a-z0-9_.:-]*$/i',
						$attribute
					);

				if(!in_array($attribute, $allowed_keys) && !$is_prefixed_attribute) {
					continue;
				}

                if($attribute_value === false || $attribute_value === null) {
                    continue;
                }

                $attributes[] = $attribute_value === true
                    ? esc_attr($attribute)
                    : sprintf(
                        '%s="%s"',
                        esc_attr($attribute),
                        esc_attr($attribute_value)
                    );
            }

            printf(
                '%s<select %s>', 
                wp_kses_post($prepend),
                implode(' ', $attributes)
            );

            foreach($options as $option_value => $option_label) {

                printf(
                    '<option value="%s"%s>%s</option>',
                    esc_attr($option_value),
                    selected($stored_value, $option_value, false),
                    esc_html($option_label)
                );

            }

            printf(
                '</select>%s', 
                wp_kses_post($append)
            );
        }



        public static function min_max(
            $args = []
        ) {

            if(!is_array($args)) {
                write_log('Param "args" must be an array in "dy_select_abstract".');
                return '';
            }

            if(!array_key_exists('min', $args)) {
                write_log('Property $args["min"] not found in "dy_select_abstract".');
                return '';
            }

            if(!array_key_exists('max', $args)) {
                write_log('Property $args["max"] not found in "dy_select_abstract".');
                return '';
            }

            if(!array_key_exists('step', $args)) {
                write_log('Property $args["step"] not found in "dy_select_abstract".');
                return '';
            }

            $min  = $args['min'];
            $max  = $args['max'];
            $step = $args['step'];

            if(
                filter_var($min, FILTER_VALIDATE_INT) === false ||
                filter_var($max, FILTER_VALIDATE_INT) === false
            ) {
                write_log('Options min and max must be integers in dy_select_abstract.');
                return '';
            }

            if(
                filter_var($step, FILTER_VALIDATE_INT) === false ||
                $step <= 0
            ) {
                write_log('Option step must be an integer greater than 0 in dy_select_abstract.');
                return '';
            }

            if($min > $max) {
                write_log('Option min cannot be greater than max in dy_select_abstract.');
                return '';
            }

           $args["options"] = [];

            for($i = $min; $i <= $max; $i += $step) {
                $args["options"][$i] = $i;
            }

            unset($args['min']);
            unset($args['max']);
            unset($args['step']);

            return self::custom( $args );
        }

		private static function get_allowed_keys() {

			return [
				//// Global attributes.
				'accesskey',
				'class',
				'contenteditable',
				'dir',
				'draggable',
				'hidden',
				'id',
				'inputmode',
				'lang',
				'role',
				'spellcheck',
				'style',
				'tabindex',
				'title',

				// Select attributes.
                'autocomplete',
                'autofocus',
                'disabled',
                'form',
                'multiple',
                'name',
                'required',
                'size',
			];

		}


    }
}