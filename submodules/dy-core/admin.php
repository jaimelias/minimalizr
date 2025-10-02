<?php

if ( !defined( 'WPINC' ) ) exit;

#[AllowDynamicProperties]
class Dynamic_Core_Admin {
    
    public function __construct()
    {
		$this->plugin_dir_url_file = plugin_dir_url( __FILE__ );
		$this->plugin_dir = plugin_dir_url( __DIR__ );

		if(is_in_theme())
		{
			$this->plugin_dir_url_file = get_stylesheet_directory_uri().'/submodules/dy-core/';
			$this->plugin_dir = get_template_directory().'/submodules/dy-core/';
		}
		
        $this->plugin_name = 'Dynamic Core';
        $this->slug = 'dy-core';
        $this->setting_id = 'dy_core_settings';
        $this->section_company = 'dy_core_section_company';
        $this->section_security = 'dy_core_section_security';
        $this->section_analytics = 'dy_core_section_analytics';
        add_action('admin_init', array(&$this, 'settings_init'), 1);
        add_action('admin_menu', array(&$this, 'admin_menu'), 1);
		add_action('admin_head', array(&$this, 'args'));
		add_action('admin_enqueue_scripts', array(&$this, 'enqueue_scripts'));
		add_action('admin_enqueue_scripts', array(&$this, 'enqueue_styles'));
    }

	public function enqueue_scripts()
	{
		global $dy_load_picker_scripts;

		if(isset($dy_load_picker_scripts))
		{
			load_picker_scripts($this->plugin_dir_url_file, $this->plugin_dir);
		}

		wp_enqueue_script( 'hyperFormula', 'https://cdn.jsdelivr.net/npm/hyperformula/dist/hyperformula.full.min.js', array('jquery'), '2.6.0', true );
		wp_enqueue_script( 'handsontableJS', 'https://cdn.jsdelivr.net/npm/handsontable/dist/handsontable.full.min.js', array('jquery', 'hyperFormula'), '14', true );
		wp_enqueue_script( 'hot', $this->plugin_dir_url_file . 'js/hot.js', array('jquery', 'handsontableJS'), time(), true );

	}
	public function enqueue_styles()
	{
		global $dy_load_picker_scripts;

		if(isset($dy_load_picker_scripts))
		{
			load_picker_styles($this->plugin_dir_url_file);
		}

		wp_enqueue_style( 'handsontableCss', $this->plugin_dir_url_file . 'css/handsontable.full.min.css', array(), '14', 'all' );
	}

    public function args()
    {
        $args = array(
            'pluginUrl' => $this->plugin_dir_url_file,
            'lang' => current_language()
        );

        echo '<script>const dyCoreArgs = '.json_encode($args).';</script>';
    }	

    public function settings_init()
    {
		$default_language = default_language();
		$languages = get_languages();

        //settings - company
		register_setting($this->setting_id, 'dy_merchant_return_link', [$this, 'esc_url']);
		register_setting($this->setting_id, 'dy_email', 'sanitize_email');
		register_setting($this->setting_id, 'dy_phone', 'esc_html');
		register_setting($this->setting_id, 'dy_address', 'esc_html');
		register_setting($this->setting_id, 'dy_tax_id', 'esc_html');


		//settings - security
		register_setting($this->setting_id, 'dy_recaptcha_site_key', 'esc_html');
		register_setting($this->setting_id, 'dy_recaptcha_secret_key', 'esc_html');
        register_setting($this->setting_id, 'dy_cloudflare_api_token', 'esc_html');
        register_setting($this->setting_id, 'dy_cloudflare_account_id', 'esc_html');
        register_setting($this->setting_id, 'dy_sentry_api_key', 'sanitize_user');

		//settings - analytics
		register_setting($this->setting_id, 'dy_gtag_tracking_id', 'sanitize_user');
		register_setting($this->setting_id, 'dy_gtm_tracking_id', 'sanitize_user');
		register_setting($this->setting_id, 'dy_facebook_pixel_id', 'sanitize_user');

	

        //section
		add_settings_section($this->section_company, __('Company'), '', $this->setting_id);
		add_settings_section($this->section_security, __('Security'), '', $this->setting_id);
		add_settings_section($this->section_analytics, __('Analytics'), '', $this->setting_id);

        //fields



		add_settings_field( 
			'dy_email', 
			esc_html(__( 'Email')), 
			array(&$this, 'settings_input'), 
			$this->setting_id, 
			$this->section_company,
			array('name' => 'dy_email', 'type' => 'email')
		);

		add_settings_field( 
			'dy_phone', 
			esc_html(__('Phone')), 
			array(&$this, 'settings_input'), 
			$this->setting_id, 
			$this->section_company,
			array('name' => 'dy_phone', 'type' => 'text')
		);

		add_settings_field( 
			'dy_merchant_return_link', 
			esc_html(__( 'Country Code (2-digits)')), 
			array(&$this, 'settings_input'), 
			$this->setting_id, 
			$this->section_company,
			array('name' => 'dy_merchant_return_link', 'type' => 'text')
		);
		


		for($x = 0; $x < count($languages); $x++)
		{
			$lang = $languages[$x];

			$prefix = ($default_language === $lang) ? '' : '_'.$lang;

			//whatsapp multy languages
			register_setting($this->setting_id, 'dy_whatsapp'.$prefix, 'intval');

			add_settings_field( 
				'dy_whatsapp'.$prefix, 
				esc_html(__( 'Whatsapp').' '. strtoupper($lang)), 
				array(&$this, 'settings_input'), 
				$this->setting_id, 
				$this->section_company,
				array('name' => 'dy_whatsapp'.$prefix, 'type' => 'number')
			);
			
			//site notification multy languages
			register_setting($this->setting_id, 'dy_site_alert'.$prefix, 'wp_kses_post');

			add_settings_field( 
				'dy_site_alert'.$prefix, 
				esc_html(__( 'Site Nofification').' '. strtoupper($lang)), 
				array(&$this, 'settings_textarea'), 
				$this->setting_id, 
				$this->section_company,
				array(
					'name' => 'dy_site_alert'.$prefix, 
					'url' => 'https://onlinehtmleditor.dev/', 
					'url_text' => __('Html Editor')
				)
			);	
		}

		add_settings_field( 
			'dy_address', 
			esc_html(__( 'Address')), 
			array(&$this, 'settings_input'), 
			$this->setting_id, 
			$this->section_company,
			array('name' => 'dy_address', 'type' => 'text')
		);

		add_settings_field( 
			'dy_tax_id', 
			esc_html(__( 'Tax Identification ID')), 
			array(&$this, 'settings_input'), 
			$this->setting_id, 
			$this->section_company,
			array('name' => 'dy_tax_id', 'type' => 'text')
		);

		add_settings_field( 
			'dy_recaptcha_site_key', 
			esc_html(__( 'Recaptcha Site Key')), 
			array(&$this, 'settings_input'), 
			$this->setting_id, 
			$this->section_security,
			array('name' => 'dy_recaptcha_site_key', 'url' => 'https://www.google.com/recaptcha/admin') 
		);	

		add_settings_field( 
			'dy_recaptcha_secret_key', 
			esc_html(__( 'Recaptcha Secret Key')), 
			array(&$this, 'settings_input'), 
			$this->setting_id, 
			$this->section_security,
			array('name' => 'dy_recaptcha_secret_key', 'url' => 'https://www.google.com/recaptcha/admin') 
		);
		
		add_settings_field( 
			'dy_cloudflare_api_token', 
			esc_html(__( 'Cloudflare API Token')), 
			array(&$this, 'settings_input'), 
			$this->setting_id, 
			$this->section_security,
			array('name' => 'dy_cloudflare_api_token') 
		);
		add_settings_field( 
			'dy_cloudflare_account_id', 
			esc_html(__( 'Cloudflare Account ID')), 
			array(&$this, 'settings_input'), 
			$this->setting_id, 
			$this->section_security,
			array('name' => 'dy_cloudflare_account_id') 
		);

		add_settings_field( 
			'dy_sentry_api_key', 
			esc_html(__( 'Sentry API Key')), 
			array(&$this, 'settings_input'), 
			$this->setting_id, 
			$this->section_security,
			array('name' => 'dy_sentry_api_key') 
		);

		add_settings_field( 
			'dy_gtag_tracking_id', 
			__( 'Google - Analytics GA4 (GTAG)'), 
			array(&$this, 'settings_input'), 
			$this->setting_id, 
			$this->section_analytics,
			array('name' => 'dy_gtag_tracking_id', 'url' => 'https://analytics.google.com/') 
		);

		add_settings_field( 
			'dy_gtm_tracking_id', 
			__( 'Google - Global Tag Manager (GMT)'), 
			array(&$this, 'settings_input'), 
			$this->setting_id, 
			$this->section_analytics,
			array('name' => 'dy_gtm_tracking_id', 'url' => 'https://tagmanager.google.com/') 
		);

		add_settings_field( 
			'dy_facebook_pixel_id', 
			__( 'Facebook Pixel ID'), 
			array(&$this, 'settings_input'), 
			$this->setting_id, 
			$this->section_analytics,
			array('name' => 'dy_facebook_pixel_id', 'url' => 'https://www.facebook.com/business/tools/meta-pixel') 
		);
    }

	public function settings_input($arr){
        $name = $arr['name'];
        $url = (array_key_exists('url', $arr)) ? '<a target="_blank" rel="noopener noreferrer" href="'.esc_url($arr['url']).'">?</a>' : null;
        $type = (array_key_exists('type', $arr)) ? $arr['type'] : 'text';
        $value = ($type == 'checkbox') ? 1 : get_option($name);
        ?>
            <input 
                type="<?php echo esc_attr($type); ?>" 
                name="<?php echo esc_attr($name); ?>" 
                id="<?php echo esc_attr($name); ?>" 
                value="<?php echo esc_attr($value); ?>" <?php echo ($type == 'checkbox') ? checked( 1, get_option($name), false ) : null; ?> /> <span><?php echo $url; ?></span>
    <?php }	

	public function settings_textarea($arr){
		$name = $arr['name'];
		$url_text = (array_key_exists('url_text', $arr)) ? $arr['url_text'] : '?';
		$url = (array_key_exists('url', $arr)) ? '<a target="_blank" rel="noopener noreferrer" href="'.esc_url($arr['url']).'">'.$url_text.'</a>' : null;
		$type = (array_key_exists('type', $arr)) ? $arr['type'] : 'text';
		$value = ($type == 'checkbox') ? 1 : get_option($name);
		?>
			<div class="text-right"><?php echo $url; ?></div>
			<textarea class="width-100" rows="10" name="<?php echo esc_attr($name); ?>" id="<?php echo esc_attr($name); ?>" ><?php echo esc_textarea($value); ?></textarea>
	<?php }	

    public  function admin_menu()
    {
        add_menu_page(
            $this->plugin_name, 
            $this->plugin_name, 
            'manage_options',  
            $this->slug, 
            array(&$this, 'settings_page'), 
            'dashicons-building'
        );
    }

	public function settings_page()
	{ 
		?><div class="wrap">
		<form action="options.php" method="post">
			
			<h1><?php echo esc_html($this->plugin_name); ?></h1>	
			<?php
				settings_fields( $this->setting_id );
				do_settings_sections( $this->setting_id );
				submit_button();
			?>			
		</form>
		
		<?php
	}

}

?>