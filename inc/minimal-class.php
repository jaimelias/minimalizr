<?php

class minimalClass{
	
	function __construct()
	{
		add_action( 'save_post', array('minimalClass', 'save'));
		add_action( 'add_meta_boxes', array('minimalClass', 'metaboxes'));
		add_action( 'wp_title', array('minimalClass', 'wp_title'), 100);
		add_action( 'pre_get_document_title', array('minimalClass', 'wp_title'), 100);
	}
	public static function wp_title($title)
	{
		if(is_singular('post'))
		{
			global $polylang;
			$value = '';
			
			if(isset($polylang))
			{
				$languages = PLL()->model->get_languages_list();
				
				
				for($x = 0; $x < count($languages); $x++)
				{
					foreach($languages[$x] as $key => $lang)
					{
						if($key == 'slug' && $lang == substr(get_locale(), 0, -3))
						{
							$value = get_post_meta(get_the_ID(), 'minimal_title_mod_'.$lang, true);
						}
					}
				}
				
				if($value != '')
				{
					$title = $value;
					$title .= ' | '.esc_html(get_bloginfo( 'name' ));
				}				
			}
			else
			{
				$value = get_post_meta(get_the_ID(), 'minimal_title_mod', true);
				
				if($value != '')
				{
					$title = $value;
					$title .= ' | '.esc_html(get_bloginfo( 'name' ));
				}				
			}
		}
		
		return $title;
	}
	public static function metaboxes()
	{
		add_meta_box( 'minimal_title_mod', __( 'Title Modifier', 'textdomain' ), array('minimalClass', 'input'), 'post');
	}
	
	public static function input($post)
	{
		
		wp_nonce_field( 'minimal_title_mod_nonce', 'minimal_title_mod_nonce');
		
		global $polylang; 
		$language_list = array();		
		
		if(isset($polylang))
		{
			$languages = PLL()->model->get_languages_list();
			
			for($x = 0; $x < count($languages); $x++)
			{
				foreach($languages[$x] as $key => $lang)
				{
					if($key == 'slug')
					{
						$value = get_post_meta($post->ID, 'minimal_title_mod_'.$lang, true);
						array_push($language_list, $lang);
						echo '<p><label>'.strtoupper(esc_html($lang)).'</label><br/>';
						echo '<input class="large-text" type="text" name="minimal_title_mod_'.esc_html($lang).'" value="'.esc_html($value).'" /></p>';
					}
				}	
			}			
		}
		else
		{
			$value = get_post_meta($post->ID, 'minimal_title_mod', true);
			echo '<input class="large-text" type="text" name="minimal_title_mod" value="'.esc_html($value).'" />';
		}
		
		
	}
	public static function save($post_id)
	{
		if(isset($_POST['minimal_title_mod_nonce']))
		{
			if(wp_verify_nonce($_POST['minimal_title_mod_nonce'], 'minimal_title_mod_nonce' ))
			{
				global $polylang; 
				$language_list = array();
				
				if(isset($polylang))
				{
					$languages = PLL()->model->get_languages_list();
					
					for($x = 0; $x < count($languages); $x++)
					{
						foreach($languages[$x] as $key => $lang)
						{
							if($key == 'slug')
							{
								if(isset( $_POST['minimal_title_mod_'.$lang]))
								{
									if($_POST['minimal_title_mod_'.$lang] != '')
									{
										update_post_meta( $post_id, 'minimal_title_mod_'.$lang, esc_attr($_POST['minimal_title_mod_'.$lang] ));								
									}
								}
							}
						}
					}
				}
				else
				{
					if(isset($_POST['minimal_title_mod']))
					{
						if($_POST['minimal_title_mod'] != '')
						{
							update_post_meta($post_id, 'minimal_title_mod', sanitize_text_field($_POST['minimal_title_mod']));	
						}
					}
				}
			}			
		}
	}
}

$minimalClass = new minimalClass();

?>