<?php

/**
 * dy_input_abstract
 *
 * Renders self-populating <input> form fields whose value is read from
 * (and, by convention, intended to be saved back to) a WordPress option
 * via the "key" passed in $args. Designed to be used both on plugin/theme
 * settings pages and, when a "post_id" is supplied, inside post meta boxes.
 *
 * Each helper method (text, email, url, number, int, float, price,
 * percentage, checkbox) is a thin wrapper around the private render()
 * method that simply pre-fills sensible <input type="..."> defaults
 * before echoing the final HTML.
 *
 * Common $args keys:
 * - key      (string, required) Option name used to fetch/cache the value
 *             and used as the "name"/"id" attribute of the field.
 * - post_id  (int|null, optional) Present to signal a post-meta context;
 *             see get_value() for how this should interact with storage.
 * - value    (mixed, optional) Overrides the auto-fetched stored value
 *             (e.g. the "on" value for checkbox()).
 * - klass    (string, optional) Alias for "class"; converted internally
 *             so calling code can pass a CSS class without colliding with
 *             the "class" key used internally on the settings page.
 * - append   (string, optional) Raw HTML appended after the <input> tag
 *             (passed through wp_kses_post).
 * - prepend  (string, optional) Raw HTML prepended before the <input> tag
 *             (passed through wp_kses_post).
 * - checked  (bool, optional) For checkbox(); auto-derived from the
 *             stored value when omitted.
 * - Any other key (type, class, placeholder, min, max, step, etc.) is
 *   rendered directly as an HTML attribute on the <input> element.
 *
 * All other unrecognized attributes are escaped with esc_attr() and
 * printed as-is, so any valid HTML input attribute can be passed through.
 *
 * Usage:
 *
 *     dy_input_abstract::text([
 *         'key'         => 'company_name',
 *         'placeholder' => 'Acme Inc.',
 *         'klass'       => 'regular-text',
 *     ]);
 *
 *     dy_input_abstract::checkbox([
 *         'key'   => 'enable_feature',
 *         'value' => 1,
 *     ]);
 *
 * @package dy
 */

if ( !defined( 'WPINC' ) ) exit;

if(!class_exists('dy_input_abstract')) {
	

	abstract class dy_input_abstract {

		abstract protected static function get_value($args);

		private static function render($args = [], $defaults = []) {


			if(!is_array($args)) {
				write_log('Param "args" must be an array in dy_input_abstract.');
				return '';
			}

			
			if(!array_key_exists('key', $args)) {
				write_log('Param "args" must contain a "key" in dy_input_abstract.');
				return '';
			}

			$key = $args['key'];

			if(!is_string($key) || trim($key) === '') {
				write_log('Property "key" must be a non-empty string in dy_input_abstract.');
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
			
			$stored_value = static::get_value($args);

			$args = array_merge(
				[
					'type'  => 'text',
					'value' => $stored_value,
				],
				$defaults,
				$args,
				[
					'name' => $key,
					'id'   => $key,
				]
			);


			if($args['type'] === 'checkbox' && !array_key_exists('checked', $args)) {
				$args['checked'] = (string) $stored_value === (string) $args['value'];
			}
 

			$attributes = [];

			$allowed_keys = self::get_allowed_keys();

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

				// Input attributes.
				'accept',
				'alt',
				'autocomplete',
				'autofocus',
				'capture',
				'checked',
				'dirname',
				'disabled',
				'form',
				'formaction',
				'formenctype',
				'formmethod',
				'formnovalidate',
				'formtarget',
				'height',
				'list',
				'max',
				'maxlength',
				'min',
				'minlength',
				'multiple',
				'name',
				'pattern',
				'placeholder',
				'readonly',
				'required',
				'size',
				'src',
				'step',
				'type',
				'value',
				'width',
			];

		}


	}
}

?>