<?php

#[AllowDynamicProperties]
class Minimal_Template{
	
	function __construct()
	{
		add_action( 'save_post', [$this, 'save']);
		add_action( 'add_meta_boxes', [$this, 'metaboxes']);
		
	}

	public function metaboxes()
	{
		add_meta_box( 'minimal_title_mod', __( 'Title Modifier', 'textdomain' ), [$this, 'input'], ['post', 'page']);
	}
	
	public function input($post)
	{
		wp_nonce_field( 'minimal_title_mod_nonce', 'minimal_title_mod_nonce');

		$languages = get_languages();

		for($x = 0; $x < count($languages); $x++)
		{
			$lang = $languages[$x];
			$value = get_post_meta($post->ID, 'minimal_title_mod_'.$lang, true);
			echo '<p><label>'.strtoupper(esc_html($lang)).'</label><br/>';
			echo '<input class="large-text" type="text" name="minimal_title_mod_'.esc_attr($lang).'" value="'.esc_attr($value).'" /></p>';
		}
	}
	
	public function save($post_id)
	{
		if ( ! wp_verify_nonce( secure_post('minimal_title_mod_nonce'), 'minimal_title_mod_nonce' ) ) return;
		if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
		if ( ! current_user_can( 'edit_post', $post_id ) ) return;

		$languages = get_languages();

		foreach ( $languages as $lang ) {
			$key = 'minimal_title_mod_' . $lang;
			$value = secure_post($key);

			if ( $value !== '' ) {
				update_post_meta(
					$post_id,
					$key,
					$value
				);
			}
		}
	}
}

new Minimal_Template();

?>
