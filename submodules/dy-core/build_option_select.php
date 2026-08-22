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
		 *     'active'  => 'Active',
		 *     'pending' => 'Pending',
		 * ]);
		 */

        protected static function get_value($key, $default = '', $post_id = null) {
			
			if($post_id === null) {
				return get_option($key, $default);
			}

			return get_post_meta($post_id, $key, true) ?: $default;
        }

        public static function custom($key, $options = [], $args = []) {

            if(empty($key)) {
                return 'Param $key is required in dy_select_controller.';
            }

            if(!is_array($options)) {
                return 'Param $options must be an array in dy_select_controller.';
            }

            if(!is_array($args)) {
                return 'Param $args must be an array in dy_select_controller.';
            }

            $post_id = $args['post_id'] ?? null;
            unset($args['post_id']);

            $value = static::get_value($key, '', $post_id);

            $args = array_merge(
                $args,
                [
                    'name' => $key,
                    'id'   => $key,
                ]
            );

            $attributes = array_map(
                fn($key, $value) => sprintf(
                    '%s="%s"',
                    esc_attr($key),
                    esc_attr($value)
                ),
                array_keys($args),
                array_values($args)
            );

            printf(
                '<select %s>',
                implode(' ', $attributes)
            );

            foreach($options as $option_value => $option_label) {

                printf(
                    '<option value="%s"%s>%s</option>',
                    esc_attr($option_value),
                    selected($value, $option_value, false),
                    esc_html($option_label)
                );
            }

            echo '</select>';
        }




		/**
		 * Create numeric select options between min and max.
		 *
		 * Example:
		 *
		 * dy_select_controller::min_max('quantity', 1, 10);
		 *
		 * dy_select_controller::min_max(
		 *     'percentage',
		 *     [0, 100, 5], // min, max, step
		 * );
		 */
        public static function min_max(
            $key,
            $options_config = [],
            $args = []
        ) {

            if(!is_array($options_config)) {
                return 'Param $options_config must be an array in dy_select_controller.';
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

            if(!is_numeric($min) || !is_numeric($max)) {
                return 'Options min and max must be numeric in dy_select_controller.';
            }

            if(!is_numeric($step) || $step <= 0) {
                return 'Option step must be greater than 0 in dy_select_controller.';
            }

            if($min > $max) {
                return 'Option min cannot be greater than max in dy_select_controller.';
            }

            $options = [];

            for($i = $min; $i <= $max; $i += $step) {
                $options[$i] = $i;
            }

            return self::custom(
                $key,
                $options,
                $args
            );
        }


    }
}