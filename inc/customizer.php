<?php
#[AllowDynamicProperties]
class MyTheme_Customize {

   public static function register ( $wp_customize ) {

		//social media section	  
		$wp_customize->add_section(
			'minimalizr', array(
			'title' => "Minimalizr - ".__( 'Settings', 'minimalizr'),
			'priority' => 35,
		));
		
		//minimalizr_menu
		$wp_customize->add_setting('minimalizr_menu_weight', array(
			'type' => 'theme_mod',
			'capability' => 'edit_theme_options',
			'sanitize_callback' => 'esc_html'
			));			
		$wp_customize->add_control('minimalizr_menu_weight', array(
			'label' => __('Menu Weight', 'minimalizr'),
			'section' => 'minimalizr',
			'settings' => 'minimalizr_menu_weight',
			'type' => 'select',
			'choices' => array(
				'light' => __('Light', 'minimalizr'),
				'normal' => __('Normal', 'minimalizr'),
				'semibold' => __('Semi-bold', 'minimalizr'),
				'strong' => __('Bold', 'minimalizr')
				)
		));
		
		
		self::social_media($wp_customize);		
		//colors
		$minimalizrColors = array("contentFont", "link_textcolor", "topBg", "topFont", "sidebarBg", "sidebarFont", "footerBg", "footerFont", "footerLink", "formBg", "formFont", "inputBg", "inputFont", "inputBorder");
		//colors labels
		$minimalizrColorsL = array(
			__("Content Font", "minimalizr"),
			__("Links", "minimalizr"),
			__("Top Background", "minimalizr"),
			__("Top Font", "minimalizr"),
			__("Sub-Menu/Sidebar Background", "minimalizr"),
			__("Sub-Menu/Sidebar Font", "minimalizr"),
			__("Footer Background", "minimalizr"),
			__("Footer Font", "minimalizr"),
			__("Footer Link", "minimalizr"),
			__("Forms Background", "minimalizr"),
			__("Forms Font", "minimalizr"),
			__("Input Background", "minimalizr"),
			__("Input Font", "minimalizr"),
			__("Input Border", "minimalizr")
		);
		//colors defaults
		$minimalizrColorsD = array("#444444", "#2889c1", "#262626", "#ffffff", "#444444", "#aaaaaa", "#f7f7f7", "#444444", "#2889c1", "#eeeeee", "#444444", "#ffffff", "#444444", "#666666");
	
		for($x = 0; $x < count($minimalizrColors); $x++)
		{
			$wp_customize->add_setting( $minimalizrColors[$x], array(
				'type' => 'theme_mod',
				'default' =>	$minimalizrColorsD[$x],
				'capability' => 'edit_theme_options',
				'sanitize_callback' => 'sanitize_hex_color'
				));
		  $wp_customize->add_control( new WP_Customize_Color_Control(
			 $wp_customize,
			 'minimalizr-'.$minimalizrColors[$x],
			 array(
				'label' => $minimalizrColorsL[$x],
				'section' => 'colors',
				'settings' => $minimalizrColors[$x],
			 ) 
		  ) );
		}	
		
	  
      //4. We can also change built-in settings by modifying properties. For instance, let's make some stuff use live preview JS...
      $wp_customize->get_setting( 'blogname' )->transport = 'postMessage';
      $wp_customize->get_setting( 'background_color' )->transport = 'postMessage';
      $wp_customize->get_setting( 'contentFont' )->transport = 'postMessage';	  
      $wp_customize->get_setting( 'topBg' )->transport = 'postMessage';
      $wp_customize->get_setting( 'topFont' )->transport = 'postMessage';
      $wp_customize->get_setting( 'sidebarBg' )->transport = 'postMessage';  
      $wp_customize->get_setting( 'sidebarFont' )->transport = 'postMessage';
	  $wp_customize->get_setting( 'link_textcolor' )->transport = 'postMessage';
	  $wp_customize->get_setting( 'footerBg' )->transport = 'postMessage';
	  $wp_customize->get_setting( 'footerFont' )->transport = 'postMessage';
	  $wp_customize->get_setting( 'footerLink' )->transport = 'postMessage';
  	  $wp_customize->get_setting( 'formBg' )->transport = 'postMessage'; 
  	  $wp_customize->get_setting( 'formFont' )->transport = 'postMessage';
  	  $wp_customize->get_setting( 'inputBg' )->transport = 'postMessage';
  	  $wp_customize->get_setting( 'inputFont' )->transport = 'postMessage';
  	  $wp_customize->get_setting( 'inputBorder' )->transport = 'postMessage';

   }
   
   
	public static function social_media($wp_customize)
	{
		// social media links
		$settingId = array("disqus", "facebook", "twitter", "linkedin", "youtube", "instagram", "pinterest", "google", "tiktok");
		$settingLabel = array("Disqus Username", "Facebook URL", "Twitter URL", "LinkedIn URL", "Youtube URL", "Instagram URL", "Pinterest URL", "Google My Business URL", "TikTok URL");
		$settingDefault = array("", "https://", "https://", "https://", "https://", "https://", "https://", "https://", "https://");
		$settingSanitize = array("esc_html", "esc_url", "esc_url", "esc_url", "esc_url", "esc_url", "esc_url", "esc_url", "esc_url");
		
		for($x = 0; $x < count($settingId); $x++)
		{
			$wp_customize->add_setting( $settingId[$x], array(
				'type' => 'theme_mod',
				'default' =>	$settingDefault[$x],
				'capability' => 'edit_theme_options',
				'sanitize_callback' => $settingSanitize[$x]
				));
			$wp_customize->add_control( $settingId[$x], array(
				'label' => $settingLabel[$x],
				'section' => 'minimalizr',
				'settings' => $settingId[$x],
				'type' => 'text'
			));
		}		
	}

   public static function header_output() {
      ?>
      <!--Customizer CSS--> 
      <style type="text/css">

		/**	SMALL TO MEDIUM **/
		@media (min-width: 1px ) and (max-width: 1024px) {
			<?php self::generate_css('.minimal-navigator', 'background-color', 'sidebarBg'); ?>
			<?php self::generate_css('.minimal-top-menu > li > a', 'color', 'sidebarFont'); ?>
		}

		<?php self::generate_css('#content a:not(.pure-button), #content a:visited:not(.pure-button), #content .linkcolor', 'color', 'link_textcolor'); ?>
		<?php self::generate_css('#content', 'color', 'contentFont'); ?>   
		<?php self::generate_css('#minimal-header', 'background-color', 'topBg'); ?>
		<?php self::generate_css('#minimal-header .site-title > a, #minimal-header .site-title > a:visited, #minimal-header, .minimal-top-menu > li > a, .minimal-top-menu > li > a:visited', 'color', 'topFont'); ?>
		<?php self::generate_css('.minimal-top-menu > li.dropdown > ul.dropdown-menu li', 'background-color', 'sidebarBg'); ?>
		
		


		<?php self::generate_css('.minimal-top-menu > li.dropdown > ul.dropdown-menu, .minimal-top-menu > li.dropdown > ul.dropdown-menu li > a', 'color', 'sidebarFont'); ?>
		
		<?php self::generate_css('#footer', 'background-color', 'footerBg'); ?>	   		   
		<?php self::generate_css('#footer', 'color', 'footerFont'); ?>	   		   
		<?php self::generate_css('#footer a:not(.pure-button)', 'color', 'footerLink'); ?>
		<?php self::generate_css('#minimal-wrapper form', 'background-color', 'formBg'); ?>
		
		
		<?php self::generate_css('#minimal-wrapper form', 'color', 'formFont'); ?>

		<?php self::generate_css('input[type=text],input[type=password],input[type=email],input[type=url],input[type=date],input[type=month],input[type=time],input[type=datetime],input[type=datetime-local],input[type=week],input[type=number],input[type=search],input[type=tel],input[type=color],select,textarea, input[type=text], select', 'background-color', 'inputBg'); ?>
		
		<?php self::generate_css('input[type=text],input[type=password],input[type=email],input[type=url],input[type=date],input[type=month],input[type=time],input[type=datetime],input[type=datetime-local],input[type=week],input[type=number],input[type=search],input[type=tel],input[type=color],select,textarea, input[type=text], select', 'color', 'inputFont'); ?>

		<?php self::generate_css('input[type=text],input[type=password],input[type=email],input[type=url],input[type=date],input[type=month],input[type=time],input[type=datetime],input[type=datetime-local],input[type=week],input[type=number],input[type=search],input[type=tel],input[type=color],select,textarea, input[type=text], select', 'border-color', 'inputBorder'); ?>		
		


      </style> 
      <!--/Customizer CSS-->
		<?php
   }
   

   public static function live_preview() {
      wp_enqueue_script( 
           'mytheme-themecustomizer',
           get_template_directory_uri() . '/js/customizer.js',
           array( 'customize-preview'),
           '20130508',
           true
      );
   }

    public static function generate_css( $selector, $style, $mod_name, $prefix='', $postfix='', $echo=true ) {
      $return = '';
      $mod = get_theme_mod($mod_name);
      if ( ! empty( $mod ) ) {
         $return = sprintf('%s { %s:%s; }',
            $selector,
            $style,
            $prefix.$mod.$postfix
         );
         if ( $echo ) {
            echo $return;
         }
      }
      return $return;
    }
}

add_action( 'customize_register', array('MyTheme_Customize', 'register'));
add_action( 'wp_head', array( 'MyTheme_Customize' , 'header_output'));
add_action( 'customize_preview_init', array('MyTheme_Customize', 'live_preview'));

?>