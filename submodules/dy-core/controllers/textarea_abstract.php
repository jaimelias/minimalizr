<?php

 
/**
 * dy_textarea_abstract
 *
 * Renders a self-populating <textarea> field whose content is read from
 * a WordPress option via the "key" passed in $args. Designed to be used
 * both on plugin/theme settings pages and, when a "post_id" is supplied,
 * inside post meta boxes.
 *
 * Common $args keys:
 * - key      (string, required) Option name used to fetch/cache the
 *             content and used as the "name"/"id" attribute of the field.
 * - post_id  (int|null, optional) Present to signal a post-meta context;
 *             see get_value() for how this should interact with storage.
 * - klass    (string, optional) Alias for "class"; converted internally
 *             so calling code can pass a CSS class without colliding with
 *             the "class" key used internally on the settings page.
 * - append   (string, optional) Raw HTML appended after the </textarea>
 *             tag (passed through wp_kses_post).
 * - prepend  (string, optional) Raw HTML prepended before the <textarea>
 *             tag (passed through wp_kses_post).
 * - Any other key (class, rows, cols, placeholder, etc.) is rendered
 *   directly as an HTML attribute on the <textarea> element.
 *
 * The stored value is escaped with esc_textarea() and printed as the
 * element's inner content, not as a "value" attribute.
 *
 * Usage:
 *
 *     dy_textarea_abstract::text([
 *         'key'  => 'company_bio',
 *         'rows' => 5,
 *         'klass' => 'large-text',
 *     ]);
 *
 * @package dy
 */

if ( !defined( 'WPINC' ) ) exit;

if(!class_exists('dy_textarea_abstract')) {

	abstract class dy_textarea_abstract {

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

				unset($args['klass']);
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