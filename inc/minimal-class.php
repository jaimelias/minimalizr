<?php

#[AllowDynamicProperties]
class Minimal_Template{
	
	function __construct()
	{
		add_action( 'save_post', array(&$this, 'save'));
		add_action( 'add_meta_boxes', array(&$this, 'metaboxes'));
		add_action( 'wp_title', array(&$this, 'wp_title'), 100);
		add_action( 'pre_get_document_title', array(&$this, 'wp_title'), 100);
	}
	public function wp_title($title)
	{
		if(is_singular('post') || is_page())
		{
			$value = '';
			$the_id = get_the_ID();
			$languages = get_languages();
			$current_language = current_language();
			
			for($x = 0; $x < count($languages); $x++)
			{
				$lang = $languages[$x];

				if($lang === $current_language)
				{
					$value = get_post_meta($the_id, 'minimal_title_mod_'.$lang, true);
				}
			}
			
			if(!empty($value))
			{
				$title = $value . ' | '. get_bloginfo( 'name' );
			}
		}
		
		return $title;
	}
	public function metaboxes()
	{
		add_meta_box( 'minimal_title_mod', __( 'Title Modifier', 'textdomain' ), array(&$this, 'input'), array('post', 'page'));
	}
	
	public function input($post)
	{
		wp_nonce_field( 'minimal_title_mod_nonce', 'minimal_title_mod_nonce');
		$languages = get_languages();
		$current_language = current_language();
		
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
		if(isset($_POST['minimal_title_mod_nonce']))
		{
			if(wp_verify_nonce($_POST['minimal_title_mod_nonce'], 'minimal_title_mod_nonce' ))
			{
				$languages = get_languages();
				$current_language = current_language();
				
				for($x = 0; $x < count($languages); $x++)
				{
					$lang = $languages[$x];

					if(isset( $_POST['minimal_title_mod_'.$lang]))
					{
						if(!empty($_POST['minimal_title_mod_'.$lang]))
						{
							update_post_meta( $post_id, 'minimal_title_mod_'.$lang, esc_attr($_POST['minimal_title_mod_'.$lang] ));								
						}
					}	
				}
			}			
		}
	}
}

new Minimal_Template();

?>