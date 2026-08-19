<?php

if(!class_exists('dy_build_option_input')) {

	class dy_build_option_input {

		private static function render($name, $args = []) {

			if(empty($name)) {
				return 'Param $name is required in dy_build_option_input.';
			}

			if(!is_array($args)) {
				return 'Param $args must be an array in dy_build_option_input.';
			}

			$value = (string) get_option($name, '');

			$defaults = [
				'type'  => 'text',
				'name'  => $name,
				'id'    => $name,
				'value' => $value,
			];

			$args = array_merge($defaults, $args);

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
				'<input %s />',
				implode(' ', $attributes)
			);
		}


		public static function text($name, $args = []) {

			self::render($name, array_merge([
				'type' => 'text',
			], $args));
		}


		public static function email($name, $args = []) {

			self::render($name, array_merge([
				'type' => 'email',
			], $args));
		}


		public static function url($name, $args = []) {

			self::render($name, array_merge([
				'type' => 'url',
			], $args));
		}


		public static function number($name, $args = []) {

			self::render($name, array_merge([
				'type' => 'number',
			], $args));
		}


		public static function int($name, $args = []) {

			self::render($name, array_merge([
				'type' => 'number',
				'step' => 1,
			], $args));
		}


		public static function float($name, $args = []) {

			self::render($name, array_merge([
				'type' => 'number',
				'step' => 'any',
			], $args));
		}


		public static function price($name, $args = []) {

			self::render($name, array_merge([
				'type' => 'number',
				'min'  => 0,
				'step' => 0.01,
			], $args));
		}


		public static function percentage($name, $args = []) {

			self::render($name, array_merge([
				'type' => 'number',
				'min'  => 0,
				'max'  => 100,
				'step' => 0.01,
			], $args));
		}
	}
}

?>