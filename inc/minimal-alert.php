<?php

if ( !defined( 'WPINC' ) ) exit;

#[AllowDynamicProperties]
class Minimal_Alert
{
    public function __construct()
    {
        $this->destination = 'dy-core';
        $this->page_slug = $this->destination . '-alerts';
        $this->title = __('Alerts', 'dycore');
        $this->section_alerts = 'dy_core_section_alerts';
        $this->setting_id = 'dy_core_settings';
        add_action('minimal_site_alert', [$this, 'site_alert']);
        add_action('minimal_footer_alert', [$this, 'footer_alert']);
        add_action('admin_menu', [$this, 'admin_menu'], 1);
        add_action('admin_init', [$this, 'settings_init'], 1);
    }

    public function admin_menu() : void {
        add_submenu_page(
            $this->destination,
            $this->title,
            $this->title,
            'manage_options',
            $this->page_slug,
            function() { $this->settings_page($this->page_slug); }
        );
    }

    public function settings_init() : void
    {
        add_settings_section(
            $this->section_alerts,
            $this->title,
            '',
            $this->page_slug
        );

        $default_language = default_language();
        $languages = get_languages();

        foreach($languages as $lang)
        {
            $lang_suffix = ($default_language === $lang) ? '' : '_' . $lang;

            register_setting(
                $this->setting_id,
                'dy_site_alert' . $lang_suffix,
                'wp_kses_post'
            );
            register_setting(
                $this->setting_id,
                'dy_footer_alert' . $lang_suffix,
                'wp_kses_post'
            );

            add_settings_field(
                'dy_site_alert' . $lang_suffix,
                esc_html(__('Site Alert', 'dycore') . ' ' . strtoupper($lang)),
                ['dy_textarea_option', 'text'],
                $this->page_slug,
                $this->section_alerts,
                [
                    'key' => 'dy_site_alert' . $lang_suffix,
                    'rows' => 5,
                    'cols' => 50,
                    'klass' => 'width-100',
                ]
            );
            add_settings_field(
                'dy_footer_alert' . $lang_suffix,
                esc_html(__('Footer Alert', 'dycore') . ' ' . strtoupper($lang)),
                ['dy_textarea_option', 'text'],
                $this->page_slug,
                $this->section_alerts,
                [
                    'key' => 'dy_footer_alert' . $lang_suffix,
                    'rows' => 5,
                    'cols' => 50,
                    'klass' => 'width-100',
                ]
            );
        }
    }

	public function settings_page(string $page)
	{ 
		?><div class="wrap">
		<form action="options.php" method="post">
			
			<h1><?php echo esc_html($this->title); ?></h1>	
			<?php
				settings_fields( $this->setting_id );
				do_settings_sections( $page );
				submit_button();
			?>			
		</form>
		
		<?php
	}

    public function site_alert() : void {
        echo $this->render_alert('site');
    }

    public function footer_alert() : void {
        echo $this->render_alert('footer');
    }

    public function render_alert($alert_id = '')
    {
        $current_language = current_language();
        $default_language = default_language();
        $prefix = ($current_language === $default_language) ? '' : '_' . $current_language;

        $decoded = trim(get_option('dy_' . $alert_id . '_alert' . $prefix));

        if($decoded === '') {
            return '';
        }

        $notification_raw = html_entity_decode($decoded);

        if ($notification_raw === '') {
            return '';
        }

        $parsed_notification = do_shortcode($notification_raw);

        $attr = esc_attr("{$alert_id}-alert");

        return sprintf(
            '<div class="minimal-%1$s" data-nosnippet><div class="minimal-%1$s-container container">%2$s</div></div>',
            $attr,
            $parsed_notification,
        );
    }
}

new Minimal_Alert();

?>