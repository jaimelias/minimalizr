<?php

if ( !defined( 'WPINC' ) ) exit;

/**
 * Alternative provider taxonomy implementation using the term-meta field
 * renderers.
 *
 * This class intentionally uses a distinct name so it can coexist with the
 * current implementation while it is reviewed. Only one provider controller
 * should be instantiated at runtime.
 */
class Dynamic_Core_Providers {

	private const TAXONOMY = 'dy-providers';
	private const META_OUTSTANDING_BALANCE = 'dy-providers_outstanding_balance';
	private const META_LANGUAGE = 'dy-providers_language';
	private const META_EMAILS = 'dy-providers_emails';
	private const META_WHATSAPP = 'dy-providers_whatsapp';

	private static $cache = [];

	public function __construct() {

		add_filter('dy_list_providers', [$this, 'get_providers']);
		add_action('init', [$this, 'register_taxonomies']);
		add_action('admin_head', [$this, 'admin_head']);
		add_action(self::TAXONOMY . '_edit_form_fields', [$this, 'form'], 10, 2);
		add_action('create_' . self::TAXONOMY, [$this, 'handle_save'], 10, 2);
		add_action('edited_' . self::TAXONOMY, [$this, 'handle_save'], 10, 2);
	}

	public function admin_head() {

		if(secure_get('taxonomy') !== self::TAXONOMY) {
			return;
		}

		echo '<style>.term-slug-wrap, .term-parent-wrap, .term-description-wrap{display: none;}</style>';
	}

	public function register_taxonomies() {

		$singular = __('Provider');
		$plural = __('Providers');

		$labels = [
			'name'              => $plural,
			'singular_name'     => $singular,
			'search_items'      => sprintf(__('Search %s'), $plural),
			'all_items'         => sprintf(__('All %s'), $plural),
			'parent_item'       => sprintf(__('Parent %s'), $singular),
			'parent_item_colon' => sprintf(__('Parent %s'), $singular),
			'edit_item'         => sprintf(__('Edit %s'), $singular),
			'update_item'       => sprintf(__('Update %s'), $singular),
			'add_new_item'      => sprintf(__('Add New %s'), $singular),
			'new_item_name'     => sprintf(__('New %s Name'), $singular),
			'menu_name'         => sprintf('🤖 %s', $plural),
		];

		register_taxonomy(
			self::TAXONOMY,
			['dy-orders', 'packages', 'aircrafts'],
			[
				'labels'            => $labels,
				'hierarchical'      => true,
				'public'            => false,
				'show_in_rest'      => true,
				'show_ui'           => true,
				'show_admin_column' => true,
				'show_in_nav_menus' => true,
				'show_tagcloud'     => true,
			]
		);
	}

	public function handle_save($term_id) {

		if(!current_user_can('edit_posts')) {
			return;
		}

		$sanitizers = [
			self::META_OUTSTANDING_BALANCE => 'sanitize_text_field',
			self::META_LANGUAGE            => 'sanitize_text_field',
			self::META_EMAILS              => static function($value) {
				return esc_textarea(dy_sanitize_email_per_line($value));
			},
			self::META_WHATSAPP            => 'sanitize_text_field',
		];

		foreach($sanitizers as $key => $sanitize) {
			if(!post_has($key)) {
				continue;
			}

			update_term_meta(
				$term_id,
				$key,
				secure_post($key, '', $sanitize)
			);
		}
	}

	public function form($term) {

		$term_id = $term->term_id;

		$this->render_form_row(
			self::META_OUTSTANDING_BALANCE,
			__('Charge balance directy to customer?'),
			static function() use ($term_id) {
				dy_select_term_meta::custom([
					'term_id' => $term_id,
					'key'     => self::META_OUTSTANDING_BALANCE,
					'options' => [
						'0' => __('No'),
						'1' => __('Yes'),
					],
				]);
			}
		);

		$language_options = [];

		foreach(get_languages() as $language) {
			$language_options[$language] = $language;
		}

		$this->render_form_row(
			self::META_LANGUAGE,
			__('Provider Language'),
			static function() use ($term_id, $language_options) {
				dy_select_term_meta::custom([
					'term_id' => $term_id,
					'key'     => self::META_LANGUAGE,
					'options' => $language_options,
				]);
			}
		);

		$this->render_form_row(
			self::META_WHATSAPP,
			__('Whatsapp'),
			static function() use ($term_id) {
				dy_input_term_meta::number([
					'term_id' => $term_id,
					'key'     => self::META_WHATSAPP,
				]);
			}
		);

		$this->render_form_row(
			self::META_EMAILS,
			__('Provider Emails'),
			static function() use ($term_id) {
				dy_textarea_term_meta::text([
					'term_id' => $term_id,
					'key'     => self::META_EMAILS,
					'rows'    => 10,
				]);
			},
			__('1 email per line. Up to 10 emails maximum.')
		);
	}

	private function render_form_row($name, $label, callable $render_field, $description = null) {

		printf(
			'<tr class="form-field"><th scope="row" valign="top"><label for="%s">%s</label></th><td>',
			esc_attr($name),
			esc_html($label)
		);

		$render_field();

		if($description) {
			printf(
				'<br/><p class="description">%s</p>',
				esc_html($description)
			);
		}

		echo '</td></tr>';
	}

	public function email_str_row_to_array($str) {

		if(!$str) {
			return [];
		}

		$emails = explode("\r\n", html_entity_decode($str));

		return array_slice(
			array_unique(array_filter(array_map('sanitize_email', $emails))),
			0,
			10
		);
	}

	public function get_providers($output = []) {

		$cache_key = self::TAXONOMY . 'get_providers';

		if(array_key_exists($cache_key, self::$cache)) {
			return self::$cache[$cache_key];
		}

		global $post;

		if(!($post instanceof WP_Post)) {
			return self::$cache[$cache_key] = $output;
		}

		$terms = get_the_terms($post->ID, self::TAXONOMY);

		if(empty($terms) || is_wp_error($terms)) {
			$terms = [];
		}

		if(property_exists($post, 'post_parent') && $post->post_parent > 0) {
			$parent_terms = get_the_terms($post->post_parent, self::TAXONOMY);

			if(!empty($parent_terms) && !is_wp_error($parent_terms)) {
				$terms = array_merge($terms, $parent_terms);
			}
		}

		$term_ids = [];

		foreach($terms as $term) {
			if(in_array($term->term_id, $term_ids)) {
				continue;
			}

			$emails = $this->email_str_row_to_array(
				get_term_meta($term->term_id, self::META_EMAILS, true)
			);

			$output[] = [
				'id'                  => $term->term_id,
				'name'                => $term->name,
				'outstanding_balance' => get_term_meta($term->term_id, self::META_OUTSTANDING_BALANCE, true),
				'language'            => get_term_meta($term->term_id, self::META_LANGUAGE, true),
				'emails'              => $emails,
				'whatsapp'            => get_term_meta($term->term_id, self::META_WHATSAPP, true),
			];

			$term_ids[] = $term->term_id;
		}

		return self::$cache[$cache_key] = $output;
	}
}
