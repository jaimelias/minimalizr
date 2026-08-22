<?php

if(!class_exists('dy_textarea_controller')) {

	class dy_textarea_controller {

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

		private static function render($key, $args = [], $defaults = []) {

			if(!is_string($key) || trim($key) === '') {
				write_log('Param $key must be a non-empty string in dy_textarea_controller.');
				return '';
			}

			if(!is_array($args)) {
				write_log('Param $args must be an array in dy_textarea_controller.');
				return '';
			}

			$post_id = $args['post_id'] ?? null;
			$append = $args['append'] ?? '';
			$prepend = $args['prepend'] ?? '';

			$value = static::get_value($key, '', $post_id);

			$args = array_merge(
				$defaults,
				$args,
				[
					'name' => $key,
					'id'   => $key,
				]
			);

			$attributes = [];

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

			return sprintf(
				'%s<textarea %s>%s</textarea>%s',
				wp_kses_post($prepend),
				implode(' ', $attributes),
				esc_textarea($value),
				wp_kses_post($append)
			);
		}


		public static function text($key, $args = []) {

			echo self::render($key, $args);
		}


	}
}

?>