<?php

if(!class_exists('dy_build_option_select')) {

	class dy_build_option_select {


        private static function render($name, $options = [], $args = []) {

            if(empty($name)) {
                return 'Param $name is required in dy_build_option_select.';
            }

            if(!is_array($options)) {
                return 'Param $options must be an array in dy_build_option_select.';
            }

            if(!is_array($args)) {
                return 'Param $args must be an array in dy_build_option_select.';
            }

            $value = sanitize_text_field((string) get_option($name, ''));

            $args = array_merge(
                $args,
                [
                    'name' => $name,
                    'id'   => $name,
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
		 * Create a select using custom options.
		 *
		 * Example:
		 *
		 * dy_build_option_select::custom('status', [
		 *     ''        => 'Select status',
		 *     'active'  => 'Active',
		 *     'pending' => 'Pending',
		 * ]);
		 */
		public static function custom($name, $options = [], $args = []) {

			self::render(
				$name,
				$options,
				$args
			);
		}


		/**
		 * Create numeric select options between min and max.
		 *
		 * Example:
		 *
		 * dy_build_option_select::min_max('quantity', 1, 10);
		 *
		 * dy_build_option_select::min_max(
		 *     'percentage',
		 *     [0, 100, 5], // min, max, step
		 * );
		 */
        public static function min_max(
            $name,
            $options_config = [],
            $args = []
        ) {

            if(!is_array($options_config)) {
                return 'Param $options_config must be an array in dy_build_option_select.';
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
                return 'Options min and max must be numeric in dy_build_option_select.';
            }

            if(!is_numeric($step) || $step <= 0) {
                return 'Option step must be greater than 0 in dy_build_option_select.';
            }

            if($min > $max) {
                return 'Option min cannot be greater than max in dy_build_option_select.';
            }

            $options = [];

            for($i = $min; $i <= $max; $i += $step) {
                $options[$i] = $i;
            }

            self::render(
                $name,
                $options,
                $args
            );
        }


    }
}