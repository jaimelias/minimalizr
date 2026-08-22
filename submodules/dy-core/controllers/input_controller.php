<?php

if ( !defined( 'WPINC' ) ) exit;

if(!class_exists('dy_input_controller')) {

	class dy_input_controller {

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

		private static function render($args = [], $defaults = []) {


			if(!is_array($args)) {
				write_log('Param $args must be an array in dy_input_controller.');
				return '';
			}

			
			if(!array_key_exists('key', $args)) {
				write_log('Param $args must contain a "key" in dy_input_controller.');
				return '';
			}

			$key = $args['key'];

			if(!is_string($key) || trim($key) === '') {
				write_log('Param $key must be a non-empty string in dy_input_controller.');
				return '';
			}

            $post_id = $args['post_id'] ?? null;
			$append = $args['append'] ?? '';
			$prepend = $args['prepend'] ?? '';

			if($post_id === null) {

				//removes the class attribute to avoid conflicts row class in the admin settings page

				if(array_key_exists('klass', $args)) {
					if(is_string($args['klass']) && trim($args['klass']) !== '') {
						$args['class'] = $args['klass'];
					}
					unset($args['klass']);
				}
			}
			
			$value = static::get_value($key, '', $post_id);

			$args = array_merge(
				[
					'type'  => 'text',
					'value' => $value,
				],
				$defaults,
				$args,
				[
					'name' => $key,
					'id'   => $key,
				]
			);


			if($args['type'] === 'checkbox' && !array_key_exists('checked', $args)) {
				$args['checked'] = (string) $value === (string) $args['value'];
			}
 

			$attributes = [];

			unset($args['key']);
			unset($args['post_id']);
			unset($args['append']);
			unset($args['prepend']);

			foreach($args as $attribute => $value) {

				if($value === false || $value === null) {
					continue;
				}

				$attributes[] = $value === true
					? esc_attr($attribute)
					: sprintf(
						'%s="%s"',
						esc_attr($attribute),
						esc_attr($value)
					);
			}

			return sprintf(
				'%s<input %s />%s',
				wp_kses_post($prepend),
				implode(' ', $attributes),
				wp_kses_post($append) 
			);
		}


		public static function text($args = []) {

			echo self::render($args);
		}


		public static function email($args = []) {

			echo self::render($args, [
				'type' => 'email',
			]);
		}


		public static function url($args = []) {

			echo self::render($args, [
				'type' => 'url',
			]);
		}


		public static function number($args = []) {

			echo self::render($args, [
				'type' => 'number',
			]);
		}


		public static function int($args = []) {

			echo self::render($args, [
				'type' => 'number',
				'step' => 1,
			]);
		}


		public static function float($args = []) {

			echo self::render($args, [
				'type' => 'number',
				'step' => 'any',
			]);
		}


		public static function price($args = []) {

			echo self::render($args, [
				'type' => 'number',
				'min'  => 0,
				'step' => 0.01,
			]);
		}


		public static function percentage($args = []) {

			echo self::render($args, [
				'type' => 'number',
				'min'  => 0,
				'max'  => 100,
				'step' => 0.01
			]);
		}


		public static function checkbox($args = []) {
 
			echo self::render($args, [
				'type'  => 'checkbox',
				'value' => 1,
			]);
		}


	}
}

?>