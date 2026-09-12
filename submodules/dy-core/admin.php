<?php

if ( !defined( 'WPINC' ) ) exit;

#[AllowDynamicProperties]
class Dynamic_Core_Admin {
    
    public function __construct(int|string $version)
    {
		$this->version = $version;
        $this->plugin_name = 'Dynamic Core';
        $this->slug = 'dy-core';
        $this->setting_id = 'dy_core_settings';
        $this->section_company = 'dy_core_section_company';
        $this->section_alerts = 'dy_core_section_alerts';
        $this->section_dev = 'dy_core_section_dev';
        $this->section_google_analytics = 'dy_core_section_google_analytics';
        $this->section_google_ads = 'dy_core_section_google_ads';
        $this->section_facebook = 'dy_core_section_facebook';
        $this->section_cloudflare_api = 'dy_core_section_cloudflare_api';
        $this->section_cloudflare_turnstile = 'dy_core_section_cloudflare_turnstile';

        $this->page_company = $this->slug . '-company';
        $this->page_alerts = $this->slug . '-alerts';
        $this->page_dev = $this->slug . '-dev';
        $this->page_google = $this->slug . '-google';
        $this->page_facebook = $this->slug . '-facebook';
        $this->page_cloudflare = $this->slug . '-cloudflare';

		$this->plugin_dir_url_file = plugin_dir_url( __FILE__ );
		$this->plugin_dir = plugin_dir_url( __DIR__ );

		if(is_in_theme())
		{
			$this->plugin_dir_url_file = get_stylesheet_directory_uri().'/submodules/dy-core/';
			$this->plugin_dir = get_template_directory().'/submodules/dy-core/';
		}

        add_action('admin_init', [$this, 'settings_init'], 1);
        add_action('admin_menu', [$this, 'admin_menu'], 1);
		add_action('admin_head', [$this, 'args']);
		add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
		add_action('admin_enqueue_scripts', [$this, 'enqueue_styles']);
    }

	public function enqueue_scripts()
	{
		global $dy_load_picker_scripts;

		if(isset($dy_load_picker_scripts))
		{
			load_picker_scripts($this->plugin_dir_url_file, $this->plugin_dir);
		}

		wp_enqueue_script( 'hyperFormula', 'https://cdn.jsdelivr.net/npm/hyperformula/dist/hyperformula.full.min.js', ['jquery'], '2.6.0', true );
		wp_enqueue_script( 'handsontableJS', 'https://cdn.jsdelivr.net/npm/handsontable/dist/handsontable.full.min.js', ['jquery', 'hyperFormula'], '14', true );
		wp_enqueue_script( 'hot', $this->plugin_dir_url_file . 'js/hot.js', ['jquery', 'handsontableJS'], $this->version, true );

	}
	public function enqueue_styles()
	{
		global $dy_load_picker_scripts;

		if(isset($dy_load_picker_scripts))
		{
			load_picker_styles($this->plugin_dir_url_file);
		}

		wp_enqueue_style( 'handsontableCss', $this->plugin_dir_url_file . 'css/handsontable.full.min.css', [], '14', 'all' );
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
	

        //settings - company
		register_setting($this->setting_id, 'dy_email', 'sanitize_email');
		register_setting($this->setting_id, 'dy_phone', 'esc_html');
		register_setting($this->setting_id, 'dy_address', 'esc_html');
		register_setting($this->setting_id, 'dy_tax_id', 'esc_html');


		//Cloudflare - Security
		register_setting($this->setting_id, 'dy_cloudflare_account_id', 'esc_html');
        register_setting($this->setting_id, 'dy_cloudflare_api_token', 'esc_html');
        register_setting($this->setting_id, 'dy_cloudflare_pdf_api_token', 'esc_html');

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
		add_settings_section($this->section_company, __('Company'), '', $this->page_company);
		add_settings_section($this->section_alerts, __('Alerts'), '', $this->page_alerts);
		add_settings_section($this->section_dev, __('Dev'), '', $this->page_dev);
		add_settings_section($this->section_google_analytics, __('Google Analytics'), '', $this->page_google);
		add_settings_section($this->section_google_ads, __('Google Ads'), '', $this->page_google);
		add_settings_section($this->section_facebook, __('Facebook'), '', $this->page_facebook);
		add_settings_section($this->section_cloudflare_turnstile, __('Cloudflare Turnstile'), '', $this->page_cloudflare);
		add_settings_section($this->section_cloudflare_api, __('Cloudflare API'), '', $this->page_cloudflare);

        //fields



		add_settings_field( 
			'dy_email', 
			esc_html(__( 'Email')), 
			['dy_input_option', 'email'], 
			$this->page_company, 
			$this->section_company,
			[
				'key' => 'dy_email',
			]
		);

		add_settings_field( 
			'dy_phone', 
			esc_html(__('Phone')), 
			['dy_input_option', 'text'], 
			$this->page_company, 
			$this->section_company,
			[
				'key' => 'dy_phone'
			]
		);

		$default_language = default_language();
		$languages = get_languages();

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
				$this->page_company, 
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
				$this->page_alerts, 
				$this->section_alerts,
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
				$this->page_alerts, 
				$this->section_alerts,
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
			$this->page_company, 
			$this->section_company,
			[
				'key' => 'dy_address'
			]
		);

		add_settings_field( 
			'dy_tax_id', 
			esc_html(__( 'Tax Identification ID')), 
			['dy_input_option', 'text'], 
			$this->page_company, 
			$this->section_company,
			[
				'key' => 'dy_tax_id'
			]
		);

		
		add_settings_field( 
			'dy_cf_turnstile_site_key', 
			esc_html(__( 'Cloudflare Turnstile Site Key')), 
			['dy_input_option', 'text'], 
			$this->page_cloudflare, 
			$this->section_cloudflare_turnstile,
			[
				'key' => 'dy_cf_turnstile_site_key'
			]
		);

		add_settings_field( 
			'dy_cf_turnstile_secret_key', 
			esc_html(__( 'Cloudflare Turnstile Secret Key')), 
			['dy_input_option', 'text'], 
			$this->page_cloudflare, 
			$this->section_cloudflare_turnstile,
			[
				'key' => 'dy_cf_turnstile_secret_key'
			]
		);
		

		add_settings_field( 
			'dy_cloudflare_account_id', 
			esc_html(__( 'Cloudflare Account ID')), 
			['dy_input_option', 'text'], 
			$this->page_cloudflare, 
			$this->section_cloudflare_api,
			[
				'key' => 'dy_cloudflare_account_id'
			]
		);

		add_settings_field( 
			'dy_cloudflare_api_token', 
			esc_html(__( 'Cloudflare API Token')), 
			['dy_input_option', 'text'], 
			$this->page_cloudflare, 
			$this->section_cloudflare_api,
			[
				'key' => 'dy_cloudflare_api_token'
			]
		);

		add_settings_field( 
			'dy_cloudflare_pdf_api_token', 
			esc_html(__( 'Cloudflare PDF API Token')), 
			['dy_input_option', 'text'], 
			$this->page_cloudflare, 
			$this->section_cloudflare_api,
			[
				'key' => 'dy_cloudflare_pdf_api_token'
			]
		);


		add_settings_field( 
			'dy_sentry_api_key', 
			esc_html(__( 'Sentry API Key')), 
			['dy_input_option', 'text'], 
			$this->page_dev, 
			$this->section_dev,
			[
				'key' => 'dy_sentry_api_key'
			]
		);

		add_settings_field( 
			'dy_gtag_tracking_id', 
			__( 'Google - Analytics GA4 (GTAG)'), 
			['dy_input_option', 'text'], 
			$this->page_google, 
			$this->section_google_analytics,
			[
				'key' => 'dy_gtag_tracking_id'
			]
		);


		add_settings_field( 
			'dy_bidding_conversion_percentage', 
			__( 'Google Adds Bidding Conversion Percentage'),
			['dy_input_option', 'percentage'], 
			$this->page_google, 
			$this->section_google_ads,
			[
				'key' => 'dy_bidding_conversion_percentage',
				'min' => 1,
				'max' => 100,
				'step' => 0.01,
				'append' => '%',
			]
		);


		add_settings_field(
			'dy_google_ads_id',
			__('Google Ads Conversion ID (AW-...)', 'dynamicpackages'),
			['dy_input_option', 'text'],
			$this->page_google,
			$this->section_google_ads,
			[
				'key' => 'dy_google_ads_id'
			]
		);

		add_settings_field(
			'dy_google_ads_purchase_label',
			__('Google Ads Purchase Label', 'dynamicpackages'),
			['dy_input_option', 'text'],
			$this->page_google,
			$this->section_google_ads,
			[
				'key' => 'dy_google_ads_purchase_label'
			]
		);

		add_settings_field(
			'dy_google_ads_lead_label',
			__('Google Ads Lead Label', 'dynamicpackages'),
			['dy_input_option', 'text'],
			$this->page_google,
			$this->section_google_ads,
			[
				'key' => 'dy_google_ads_lead_label'
			]
		);

		add_settings_field( 
			'dy_facebook_pixel_id', 
			__( 'Facebook Pixel ID'), 
			['dy_input_option', 'text'],
			$this->page_facebook, 
			$this->section_facebook,
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
            function() { $this->settings_page($this->page_company); },
            'dashicons-building'
        );

		$pages = [
			$this->page_company => __('Company'),
			$this->page_alerts => __('Alerts'),
			$this->page_dev => __('Dev'),
			$this->page_google => __('Google'),
			$this->page_facebook => __('Facebook'),
			$this->page_cloudflare => __('Cloudflare'),
		];

		foreach($pages as $page_slug => $page_title) {
			add_submenu_page(
				$this->slug,
				$page_title,
				$page_title,
				'manage_options',
				$page_slug,
				function() use ($page_slug) { $this->settings_page($page_slug); }
			);
		}
    }

	public function settings_page(string $page)
	{ 
		?><div class="wrap">
		<form action="options.php" method="post">
			
			<h1><?php echo esc_html($this->plugin_name); ?></h1>	
			<?php
				settings_fields( $this->setting_id );
				do_settings_sections( $page );
				submit_button();
			?>			
		</form>
		
		<?php
	}

}

?>
