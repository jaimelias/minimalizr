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
        add_action('admin_init', array($this, 'settings_init'), 1);
        add_action('admin_menu', array($this, 'admin_menu'), 1);
		add_action('admin_head', array($this, 'args'));
		add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
		add_action('admin_enqueue_scripts', array($this, 'enqueue_styles'));
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
            'lang' => current_language()
        );

        echo '<script>const dyCoreArgs = '.json_encode($args).';</script>';
    }	

    public function settings_init()
    {
		$default_language = default_language();
		$languages = get_languages();

        //settings - company
		register_setting($this->setting_id, 'dy_email', 'sanitize_email');
		register_setting($this->setting_id, 'dy_phone', 'esc_html');
		register_setting($this->setting_id, 'dy_address', 'esc_html');
		register_setting($this->setting_id, 'dy_tax_id', 'esc_html');


		//Cloudflare - Security
        register_setting($this->setting_id, 'dy_cloudflare_api_token', 'esc_html');
        register_setting($this->setting_id, 'dy_cloudflare_account_id', 'esc_html');

		//Cloudflare - Turnstile
		register_setting($this->setting_id, 'dy_cf_turnstile_site_key', 'esc_html');
		register_setting($this->setting_id, 'dy_cf_turnstile_secret_key', 'esc_html');

        register_setting($this->setting_id, 'dy_sentry_api_key', 'sanitize_user');

		//settings - analytics
		register_setting($this->setting_id, 'dy_bidding_conversion_percentage', function($value) {
			$value = is_numeric($value) ? (float) $value : 15; //defaults to 15% if not numeric
			return max(1, min(100, $value)); //sets min at 1% and max at 100%
		});
		
		register_setting($this->setting_id, 'dy_gtag_tracking_id', 'sanitize_user');
		register_setting($this->setting_id, 'dy_google_ads_id', 'sanitize_text_field');
		register_setting($this->setting_id, 'dy_google_ads_purchase_label', 'sanitize_text_field');
		register_setting($this->setting_id, 'dy_google_ads_lead_label', 'sanitize_text_field');
		register_setting($this->setting_id, 'dy_facebook_pixel_id', 'sanitize_user');

	

        //section
		add_settings_section($this->section_company, __('Company'), '', $this->setting_id);
		add_settings_section($this->section_security, __('Security'), '', $this->setting_id);
		add_settings_section($this->section_analytics, __('Analytics'), '', $this->setting_id);

        //fields



		add_settings_field( 
			'dy_email', 
			esc_html(__( 'Email')), 
			['dy_input_option', 'email'], 
			$this->setting_id, 
			$this->section_company,
			[
				'key' => 'dy_email',
			]
		);

		add_settings_field( 
			'dy_phone', 
			esc_html(__('Phone')), 
			['dy_input_option', 'text'], 
			$this->setting_id, 
			$this->section_company,
			[
				'key' => 'dy_phone'
			]
		);

		for($x = 0; $x < count($languages); $x++)
		{
			$lang = $languages[$x];

			$lang_suffix = ($default_language === $lang) ? '' : '_'.$lang;

			//whatsapp multy languages
			register_setting($this->setting_id, 'dy_whatsapp'.$lang_suffix, 'intval');

			add_settings_field( 
				'dy_whatsapp'.$lang_suffix, 
				esc_html(__( 'Whatsapp').' '. strtoupper($lang)), 
				['dy_input_option', 'number'], 
				$this->setting_id, 
				$this->section_company,
				[
					'key' => 'dy_whatsapp'.$lang_suffix
				]
			);
			
			//site notification multy languages
			register_setting($this->setting_id, 'dy_site_alert'.$lang_suffix, 'wp_kses_post');
			register_setting($this->setting_id, 'dy_footer_alert'.$lang_suffix, 'wp_kses_post');

			add_settings_field( 
				'dy_site_alert'.$lang_suffix, 
				esc_html(__( 'Site Alert').' '. strtoupper($lang)), 
				['dy_textarea_option', 'text'], 
				$this->setting_id, 
				$this->section_company,
				[
					'key' => 'dy_site_alert'.$lang_suffix,
					'rows' => 5,
					'cols' => 50,
					'klass' => 'width-100',
				]
			);
			add_settings_field( 
				'dy_footer_alert'.$lang_suffix, 
				esc_html(__( 'Footer Alert').' '. strtoupper($lang)), 
				['dy_textarea_option', 'text'],
				$this->setting_id, 
				$this->section_company,
				[
					'key' => 'dy_footer_alert'.$lang_suffix,
					'rows' => 5,
					'cols' => 50,
					'klass' => 'width-100',
				]
			);
			


		}

		add_settings_field( 
			'dy_address', 
			esc_html(__( 'Address')), 
			['dy_input_option', 'text'], 
			$this->setting_id, 
			$this->section_company,
			[
				'key' => 'dy_address'
			]
		);

		add_settings_field( 
			'dy_tax_id', 
			esc_html(__( 'Tax Identification ID')), 
			['dy_input_option', 'text'], 
			$this->setting_id, 
			$this->section_company,
			[
				'key' => 'dy_tax_id'
			]
		);

		
		add_settings_field( 
			'dy_cf_turnstile_site_key', 
			esc_html(__( 'Cloudflare Turnstile Site Key')), 
			['dy_input_option', 'text'], 
			$this->setting_id, 
			$this->section_security,
			[
				'key' => 'dy_cf_turnstile_site_key'
			]
		);

		add_settings_field( 
			'dy_cf_turnstile_secret_key', 
			esc_html(__( 'Cloudflare Turnstile Secret Key')), 
			['dy_input_option', 'text'], 
			$this->setting_id, 
			$this->section_security,
			[
				'key' => 'dy_cf_turnstile_secret_key'
			]
		);
		
		add_settings_field( 
			'dy_cloudflare_api_token', 
			esc_html(__( 'Cloudflare API Token')), 
			['dy_input_option', 'text'], 
			$this->setting_id, 
			$this->section_security,
			[
				'key' => 'dy_cloudflare_api_token'
			]
		);
		add_settings_field( 
			'dy_cloudflare_account_id', 
			esc_html(__( 'Cloudflare Account ID')), 
			['dy_input_option', 'text'], 
			$this->setting_id, 
			$this->section_security,
			[
				'key' => 'dy_cloudflare_account_id'
			]
		);

		add_settings_field( 
			'dy_sentry_api_key', 
			esc_html(__( 'Sentry API Key')), 
			['dy_input_option', 'text'], 
			$this->setting_id, 
			$this->section_security,
			[
				'key' => 'dy_sentry_api_key'
			]
		);

		add_settings_field( 
			'dy_bidding_conversion_percentage', 
			__( 'Bidding Conversion Percentage'),
			['dy_input_option', 'percentage'], 
			$this->setting_id, 
			$this->section_analytics,
			[
				'key' => 'dy_bidding_conversion_percentage',
				'min' => 1,
				'max' => 100,
				'step' => 0.01,
				'append' => '%',
			]
		);


		add_settings_field( 
			'dy_gtag_tracking_id', 
			__( 'Google - Analytics GA4 (GTAG)'), 
			['dy_input_option', 'text'], 
			$this->setting_id, 
			$this->section_analytics,
			[
				'key' => 'dy_gtag_tracking_id'
			]
		);

		add_settings_field(
			'dy_google_ads_id',
			__('Google Ads Conversion ID (AW-...)', 'dynamicpackages'),
			['dy_input_option', 'text'],
			$this->setting_id,
			$this->section_analytics,
			[
				'key' => 'dy_google_ads_id'
			]
		);

		add_settings_field(
			'dy_google_ads_purchase_label',
			__('Google Ads Purchase Label', 'dynamicpackages'),
			['dy_input_option', 'text'],
			$this->setting_id,
			$this->section_analytics,
			[
				'key' => 'dy_google_ads_purchase_label'
			]
		);

		add_settings_field(
			'dy_google_ads_lead_label',
			__('Google Ads Lead Label', 'dynamicpackages'),
			['dy_input_option', 'text'],
			$this->setting_id,
			$this->section_analytics,
			[
				'key' => 'dy_google_ads_lead_label'
			]
		);

		add_settings_field( 
			'dy_facebook_pixel_id', 
			__( 'Facebook Pixel ID'), 
			['dy_input_option', 'text'],
			$this->setting_id, 
			$this->section_analytics,
			[
				'key' => 'dy_facebook_pixel_id'
			]		
		);
    }

    public  function admin_menu()
    {
        add_menu_page(
            $this->plugin_name, 
            $this->plugin_name, 
            'manage_options',  
            $this->slug, 
            array($this, 'settings_page'), 
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