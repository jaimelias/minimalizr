<?php

if(!class_exists('dy_select_controller')) {

	class dy_select_controller {


		/**
		 * Create a select using custom options.
		 *
		 * Example:
		 *
		 * dy_select_controller::custom('status', [
		 *     ''        => 'Select status',
		 *     '0'  => 'Active',
		 *     '1' => 'Pending',
		 * ]);
         * 
		 * dy_select_controller::min_max('status', [
		 *     'min'        => 0,
		 *     'max'        => 100,
		 *     'step'       => 1,
		 * ]);
		 */


		private static $cache = [];

		protected static function get_value($key, $default = '', $post_id = null) {

			$cache_key = $key . '_' . ($post_id ?? 'option');

			if (array_key_exists($cache_key, self::$cache)) {
				return self::$cache[$cache_key]; 
			}

			if($post_id === null) {
				return self::$cache[$cache_key] = get_option($key, $default);
			}

			$value = get_post_meta($post_id, $key, true);

			return self::$cache[$cache_key] = $value === '' ? $default : $value;
		}

        public static function custom($options = [], $args = []) {

            if(!is_array($args)) {
                write_log('Param $args must be an array in dy_select_controller.');
                return '';
            }

			if(!array_key_exists('key', $args)) {
				write_log('Param $args must contain a "key" in dy_input_controller.');
				return '';
			}

            $key = $args['key'];

            if(!is_string($key) || trim($key) === '') {
                write_log('Param $key must be a non-empty string in dy_select_controller.');
                return '';
            }

            if(!is_array($options)) {
                write_log('Param $options must be an array in dy_select_controller.');
                return '';
            }


            $post_id = $args['post_id'] ?? null;
			$append = $args['append'] ?? '';
			$prepend = $args['prepend'] ?? '';
			


            $selected_value = static::get_value($key, '', $post_id);

            $args = array_merge(
                $args,
                [
                    'name' => $key,
                    'id'   => $key,
                ]
            );

            $attributes = [];

			unset($args['key']);
			unset($args['post_id']);
			unset($args['append']);
			unset($args['prepend']);

            foreach($args as $attribute => $attribute_value) {

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
                    selected($selected_value, $option_value, false),
                    esc_html($option_label)
                );

            }

            printf(
                '</select>%s', 
                wp_kses_post($append)
            );
        }



        public static function min_max(
            $options_config = [],
            $args = []
        ) {

            if(!is_array($options_config)) {
                write_log('Param $options_config must be an array in dy_select_controller.');
                return '';
            }

            $defaults = [
                'min'  => 0,
                'max'  => 100,
                'step' => 1,
            ];

            $options_config = array_merge(
                $defaults,
                $options_config
            );

            $min  = $options_config['min'];
            $max  = $options_config['max'];
            $step = $options_config['step'];

            if(
                filter_var($min, FILTER_VALIDATE_INT) === false ||
                filter_var($max, FILTER_VALIDATE_INT) === false
            ) {
                write_log('Options min and max must be integers in dy_select_controller.');
                return '';
            }

            if(
                filter_var($step, FILTER_VALIDATE_INT) === false ||
                $step <= 0
            ) {
                write_log('Option step must be an integer greater than 0 in dy_select_controller.');
                return '';
            }

            if($min > $max) {
                write_log('Option min cannot be greater than max in dy_select_controller.');
                return '';
            }

            $options = [];

            for($i = $min; $i <= $max; $i += $step) {
                $options[$i] = $i;
            }

            return self::custom(
                $options,
                $args
            );
        }


    }
}