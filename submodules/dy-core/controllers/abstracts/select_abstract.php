<?php

/**
 * Base renderer for value-backed HTML <select> elements.
 *
 * Concrete subclasses must implement get_value() to retrieve the currently
 * selected value from a storage provider. Do not invoke this abstract class
 * directly; use a concrete implementation such as dy_select_option.
 *
 * Public methods:
 * - custom()  Renders options supplied as value => label pairs.
 * - min_max() Generates a numeric range and delegates rendering to custom().
 *
 * Arguments accepted by custom():
 * - key     (string, required) Storage key and generated select name/id.
 * - options (array, required)  Option values mapped to visible labels.
 * - klass   (string, optional) Converted to the HTML "class" attribute.
 * - prepend (string, optional) HTML rendered before the select.
 * - append  (string, optional) HTML rendered after the select.
 *
 * Additional arguments required by min_max():
 * - min  (int) Lower inclusive range boundary.
 * - max  (int) Upper inclusive range boundary.
 * - step (int) Positive range increment.
 *
 * Only allowlisted global and <select> attributes are rendered. Valid data-*
 * and aria-* attributes are accepted. Internal arguments, option definitions,
 * and event-handler attributes are not rendered.
 *
 * Attribute values and option values are escaped with esc_attr(). Option
 * labels are escaped with esc_html(). Prepend and append content are filtered
 * with wp_kses_post().
 *
 * Public methods echo the generated markup.
 *
 * Example:
 *
 *     dy_select_option::custom([
 *         'key'     => 'status',
 *         'klass'   => 'regular-select',
 *         'required' => true,
 *         'options' => [
 *             'active'   => 'Active',
 *             'disabled' => 'Disabled',
 *         ],
 *         'data-controller' => 'status-field',
 *         'aria-label'      => 'Status',
 *     ]);
 *
 * Numeric range example:
 *
 *     dy_select_option::min_max([
 *         'key'  => 'percentage',
 *         'min'  => 0,
 *         'max'  => 100,
 *         'step' => 10,
 *     ]);
 *
 * @package dy
 */

if ( !defined( 'WPINC' ) ) exit;

if(!class_exists('dy_select_abstract')) {

	abstract class dy_select_abstract {

        /**
         * Retrieves the stored value for the field.
         *
         * Concrete subclasses must implement this method and return the value supplied
         * by their storage provider.
         *
         * The "key" argument has already been validated as a non-empty string before
         * this method is called.
         *
         * @param array<string, mixed> $args Field configuration containing "key".
         * @return mixed Stored field value.
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

			if(array_key_exists('label', $args)) {
				if(is_string($args['label']) && trim($args['label']) !== '' ) {
					$prepend .= sprintf(
						'<label for="%s">%s</label><br />',
						esc_attr( $key ),
						esc_html($args['label'] )
					);
				}
			}

			
            //replaces the current class by the attribute of class
            if(array_key_exists('klass', $args)) {
                if(is_string($args['klass']) && trim($args['klass']) !== '') {
                    $args['class'] = $args['klass'];
                }
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
            $allowed_keys = self::get_allowed_keys();

            foreach($args as $attribute => $attribute_value) {

				$is_prefixed_attribute = is_string($attribute)
					&& preg_match(
						'/^(?:data|aria)-[a-z][a-z0-9_.:-]*$/i',
						$attribute
					);

				if(!in_array($attribute, $allowed_keys, true) && !$is_prefixed_attribute) {
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