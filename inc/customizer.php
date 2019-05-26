<?php

class MyTheme_Customize {

   public static function register ( $wp_customize ) {

		//social media section	  
		$wp_customize->add_section(
			'minimalizr', array(
			'title' => "Minimalizr - ".__( 'Settings', 'minimalizr'),
			'priority' => 35,
		));

		//minimal logo
		$wp_customize->add_setting('minimalizr_large_icon');
		$wp_customize->add_setting('minimalizr_fav_icon');
		$wp_customize->add_control( new WP_Customize_Image_Control( $wp_customize, 'minimalizr_large_icon', array(
			'label'    => __( 'Large Icon', 'minimalizr'),
			'section'  => 'minimalizr',
			'settings' => 'minimalizr_large_icon',
		) ) );	
		$wp_customize->add_control( new WP_Customize_Image_Control( $wp_customize, 'minimalizr_fav_icon', array(
			'label'    => __( 'Favicon', 'minimalizr'),
			'section'  => 'minimalizr',
			'settings' => 'minimalizr_fav_icon',
		) ) );			
	  
		//minimalizr_menu
		$wp_customize->add_setting('minimalizr_menu', array(
			'type' => 'theme_mod',
			'capability' => 'edit_theme_options',
			'sanitize_callback' => 'esc_html'
			));		
		$wp_customize->add_control('minimalizr_menu', array(
			'label' => __('Menu Type', 'minimalizr'),
			'section' => 'minimalizr',
			'settings' => 'minimalizr_menu',
			'type' => 'select',
			'choices' => array(
				'minimal' => __('Minimal', 'minimalizr'),
				'responsive' => __('Responsive', 'minimalizr')
				)
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
				'bold' => __('Bold', 'minimalizr')
				)
		));
		
		
		self::social_media($wp_customize);
		self::contact($wp_customize);
		
		//colors
		$minimalizrColors = array("contentFont", "link_textcolor", "topBg", "topFont", "sidebarBg", "sidebarFont", "footerBg", "footerFont", "footerLink", "formBg", "formFont", "inputBg", "inputFont", "inputBorder");
		//colors labels
		$minimalizrColorsL = array(	__("Content Font", "minimalizr"),
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

	$analytics = array('analytics_tracking_id', 'tagmanager_container_id', 'google_optimize_container_id', 'facebook_pixel_id');
	$analytics_label = array('Analytics Tracking ID', 'Tag Manager Container ID', 'Optimize Container ID', 'Facebook Pixel ID');

	  $wp_customize->add_section( 'minimalizr_anlytics', array(
		'title'          => __( 'Analytics', 'minimalizr'),
		'priority'       => 34,
		));  
		
		for($x = 0; $x < count($analytics); $x++)
		{
			$wp_customize->add_setting( $analytics[$x], array(
				'type' => 'theme_mod',
				'default' =>	'',
				'capability' => 'edit_theme_options',
				'sanitize_callback' => 'esc_html'
				));
			$wp_customize->add_control( $analytics[$x], array(
				'label' => $analytics_label[$x],
				'section' => 'minimalizr_anlytics',
				'settings' => $analytics[$x],
				'type' => 'text'
			));
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
   
   public static function contact($wp_customize)
   {
		// social media links
		$settingId = array('whatsapp', 'messenger', 'skype');
		$settingLabel = array('WhatsApp Number', 'Messenger Username', 'Skype Username');
		$settingDefault = array('', '', '');
		$settingSanitize = array('esc_html', 'esc_html', 'esc_html');
		
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
   
	public static function social_media($wp_customize)
	{
		// social media links
		$settingId = array("disqus", "min_tel", "min_address", "facebook", "twitter", "linkedin", "youtube", "instagram", "pinterest", "google");
		$settingLabel = array("Disqus Username", "Company Telephone", "Company Address", "Facebook URL", "Twitter URL", "LinkedIn URL", "Youtube URL", "Instagram URL", "Pinterest URL", "Google My Business URL");
		$settingDefault = array("", "", "", "https://", "https://", "https://", "https://", "https://", "https://", "https://");
		$settingSanitize = array("esc_html", "esc_html", "esc_html", "esc_url", "esc_url", "esc_url", "esc_url", "esc_url", "esc_url", "esc_url");
		
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
		<?php self::generate_css('a:not(.pure-button), a:visited:not(.pure-button), .linkcolor', 'color', 'link_textcolor'); ?>
		<?php self::generate_css('#content', 'color', 'contentFont'); ?>   
		<?php self::generate_css('.minimal-header', 'background-color', 'topBg'); ?>
		<?php self::generate_css('.minimal-header, .minimal-header a, .minimal-header a:visited', 'color', 'topFont'); ?>
		<?php self::generate_css('.top_menu > li.dropdown > ul.dropdown-menu li, .minimal .top_navigator, body.toggled .responsive .top_navigator', 'background-color', 'sidebarBg'); ?>
		<?php self::generate_css('.responsive .top_menu > li.dropdown > ul.dropdown-menu, .responsive .top_menu > li.dropdown > ul.dropdown-menu a, .minimal .top_menu li a, .minimal .top_menu, body.toggled .responsive .top_navigator a', 'color', 'sidebarFont'); ?>
		<?php self::generate_css('#footer', 'background-color', 'footerBg'); ?>	   		   
		<?php self::generate_css('#footer', 'color', 'footerFont'); ?>	   		   
		<?php self::generate_css('#footer a:not(.pure-button)', 'color', 'footerLink'); ?>
		<?php self::generate_css('#wrapper form', 'background-color', 'formBg'); ?>
		
		
		<?php self::generate_css('#wrapper form', 'color', 'formFont'); ?>

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