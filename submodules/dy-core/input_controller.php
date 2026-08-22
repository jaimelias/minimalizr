<?php

if(!class_exists('dy_input_controller')) {

	class dy_input_controller {

        protected static function get_value($key, $default = '', $post_id = null) {
			
			if($post_id === null) {
				return get_option($key, $default);
			}

			return get_post_meta($post_id, $key, true) ?: $default;
        }

		private static function render($key, $args = []) {

			if(empty($key)) {
				return 'Param $key is required in dy_input_controller.';
			}

			if(!is_array($args)) {
				return 'Param $args must be an array in dy_input_controller.';
			}

            $post_id = $args['post_id'] ?? null;
            unset($args['post_id']);

            $value = static::get_value($key, '', $post_id);

			$args = array_merge(
				[
					'type'  => 'text',
					'value' => $value,
				],
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
				'<input %s />',
				implode(' ', $attributes)
			);
		}


		public static function text($key, $args = []) {

			self::render($key, array_merge([
				'type' => 'text',
			], $args));
		}


		public static function email($key, $args = []) {

			self::render($key, array_merge([
				'type' => 'email',
			], $args));
		}


		public static function url($key, $args = []) {

			self::render($key, array_merge([
				'type' => 'url',
			], $args));
		}


		public static function number($key, $args = []) {

			self::render($key, array_merge([
				'type' => 'number',
			], $args));
		}


		public static function int($key, $args = []) {

			self::render($key, array_merge([
				'type' => 'number',
				'step' => 1,
			], $args));
		}


		public static function float($key, $args = []) {

			self::render($key, array_merge([
				'type' => 'number',
				'step' => 'any',
			], $args));
		}


		public static function price($key, $args = []) {

			self::render($key, array_merge([
				'type' => 'number',
				'min'  => 0,
				'step' => 0.01,
			], $args));
		}


		public static function percentage($key, $args = []) {

			self::render($key, array_merge([
				'type' => 'number',
				'min'  => 0,
				'max'  => 100,
				'step' => 0.01,
			], $args));
		}
	}
}

?>