<?php

 
/**
 * Base renderer for value-backed HTML <textarea> elements.
 *
 * This class provides shared validation, attribute filtering, escaping, and
 * rendering logic. Concrete subclasses must implement get_value() to define
 * the field's storage provider.
 *
 * Do not invoke this abstract class directly. Use a concrete implementation
 * such as dy_textarea_option.
 *
 * Supported arguments:
 * - key     (string, required) Storage key and generated textarea name/id.
 * - klass   (string, optional) Converted to the HTML "class" attribute.
 * - prepend (string, optional) HTML rendered before the textarea.
 * - append  (string, optional) HTML rendered after the textarea.
 *
 * Allowlisted global and <textarea> attributes such as rows, cols, required,
 * maxlength, and placeholder are rendered. Valid data-* and aria-* attributes
 * are also accepted. Internal and event-handler attributes are rejected.
 *
 * The retrieved value is rendered as the textarea content and escaped with
 * esc_textarea(). Attribute values are escaped with esc_attr(). Prepend and
 * append content are filtered with wp_kses_post().
 *
 * text() echoes the generated markup.
 *
 * Example:
 *
 *     dy_textarea_option::text([
 *         'key'             => 'company_bio',
 *         'klass'           => 'large-text',
 *         'rows'            => 5,
 *         'data-controller' => 'company-bio',
 *         'aria-label'      => 'Company biography',
 *     ]);
 *
 * @package dy
 */

if ( !defined( 'WPINC' ) ) exit;

if(!class_exists('dy_textarea_abstract')) {

	abstract class dy_textarea_abstract {


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
				write_log('Param "args" must be an array in dy_textarea_abstract.');
				return '';
			}

			if(!array_key_exists('key', $args)) {
				write_log('Param "args" must contain a "key" in dy_input_option.');
				return '';
			}

			$key = $args['key'];

			if(!is_string($key) || trim($key) === '') {
				write_log('Property "key" must be a non-empty string in dy_textarea_abstract.');
				return '';
			}


			$append = $args['append'] ?? '';
			$prepend = $args['prepend'] ?? '';

			//replaces the current class by the attribute of class
			if(array_key_exists('klass', $args)) {
				if(is_string($args['klass']) && trim($args['klass']) !== '') {
					$args['class'] = $args['klass'];
				}
			}

			$stored_value = static::get_value($args);

			$args = array_merge(
				$defaults,
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

			return sprintf(
				'%s<textarea %s>%s</textarea>%s',
				wp_kses_post($prepend),
				implode(' ', $attributes),
				esc_textarea($stored_value),
				wp_kses_post($append)
			);
		}


		public static function text($args = []) {

			echo self::render($args);
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

				// Textarea attributes.
				'autocomplete',
				'autofocus',
				'cols',
				'dirname',
				'disabled',
				'form',
				'maxlength',
				'minlength',
				'name',
				'placeholder',
				'readonly',
				'required',
				'rows',
				'wrap',
			];

		}


	}
}

?>