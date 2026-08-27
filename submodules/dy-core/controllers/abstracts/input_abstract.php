<?php

/**
 * Base renderer for value-backed HTML <input> elements.
 *
 * This class implements the shared validation, attribute filtering, escaping,
 * and rendering logic for input fields. Concrete subclasses must implement
 * get_value() to define where the field's stored value comes from.
 *
 * Do not call this abstract class directly. Use a concrete implementation such
 * as dy_input_option or create a subclass for another storage provider.
 *
 * Available field helpers:
 * - text()
 * - email()
 * - url()
 * - number()
 * - int()
 * - float()
 * - price()
 * - percentage()
 * - checkbox()
 *
 * Supported arguments:
 * - key     (string, required) Storage key and generated field name/id.
 * - value   (mixed, optional) Overrides the retrieved value.
 * - klass   (string, optional) Converted to the HTML "class" attribute.
 * - prepend (string, optional) HTML rendered before the input.
 * - append  (string, optional) HTML rendered after the input.
 * - checked (bool, optional) Explicit checkbox state. When omitted, the
 *                            retrieved value is compared with "value".
 *
 * Only allowlisted global and <input> attributes are rendered. Valid data-*
 * and aria-* attributes are also accepted. Internal controller arguments and
 * event-handler attributes such as onclick are not rendered.
 *
 * Attribute names and values are escaped with esc_attr(). Prepend and append
 * content are filtered with wp_kses_post().
 *
 * Public helpers echo the generated markup.
 *
 * Example:
 *
 *     dy_input_option::text([
 *         'key'             => 'company_name',
 *         'klass'           => 'regular-text',
 *         'placeholder'     => 'Acme Inc.',
 *         'data-controller' => 'company-field',
 *         'aria-label'      => 'Company name',
 *     ]);
 *
 * @package dy
 */

if ( !defined( 'WPINC' ) ) exit;

if(!class_exists('dy_input_abstract')) {
	

	abstract class dy_input_abstract {


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


			if(array_key_exists('label', $args)) {
				if(is_string($args['label']) && trim($args['label']) !== '' ) {
					$prepend = sprintf(
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