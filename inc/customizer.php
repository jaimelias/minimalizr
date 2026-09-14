<?php

class MyTheme_Customize {

	public static function register( WP_Customize_Manager $wp_customize ): void {
		$wp_customize->add_section(
			'minimalizr',
			[
				'title'    => sprintf( 'Minimalizr - %s', __( 'Settings', 'minimalizr' ) ),
				'priority' => 35,
			]
		);

		$wp_customize->add_setting(
			'minimalizr_menu_weight',
			[
				'type'              => 'theme_mod',
				'default'           => 'normal',
				'capability'        => 'edit_theme_options',
				'sanitize_callback' => [ self::class, 'sanitize_menu_weight' ],
			]
		);

		$wp_customize->add_control(
			'minimalizr_menu_weight',
			[
				'label'    => __( 'Menu Weight', 'minimalizr' ),
				'section'  => 'minimalizr',
				'settings' => 'minimalizr_menu_weight',
				'type'     => 'select',
				'choices'  => [
					'light'    => __( 'Light', 'minimalizr' ),
					'normal'   => __( 'Normal', 'minimalizr' ),
					'semibold' => __( 'Semi-bold', 'minimalizr' ),
					'strong'   => __( 'Bold', 'minimalizr' ),
				],
			]
		);

		self::social_media( $wp_customize );
		self::register_existing_colors( $wp_customize );
		self::register_minimal_box( $wp_customize );
		self::register_alert_sections( $wp_customize );
		self::set_post_message_settings( $wp_customize );
	}

	public static function sanitize_menu_weight( string $value ): string {
		$allowed = [ 'light', 'normal', 'semibold', 'strong' ];

		return in_array( $value, $allowed, true ) ? $value : 'normal';
	}

	public static function social_media( WP_Customize_Manager $wp_customize ): void {
		$settings = [
			'facebook'  => __( 'Facebook URL', 'minimalizr' ),
			'twitter'   => __( 'Twitter URL', 'minimalizr' ),
			'linkedin'  => __( 'LinkedIn URL', 'minimalizr' ),
			'youtube'   => __( 'YouTube URL', 'minimalizr' ),
			'instagram' => __( 'Instagram URL', 'minimalizr' ),
			'pinterest' => __( 'Pinterest URL', 'minimalizr' ),
			'google'    => __( 'Google My Business URL', 'minimalizr' ),
			'tiktok'    => __( 'TikTok URL', 'minimalizr' ),
		];

		foreach ( $settings as $setting_id => $label ) {
			$wp_customize->add_setting(
				$setting_id,
				[
					'type'              => 'theme_mod',
					'default'           => '',
					'capability'        => 'edit_theme_options',
					'sanitize_callback' => 'esc_url_raw',
				]
			);

			$wp_customize->add_control(
				$setting_id,
				[
					'label'    => $label,
					'section'  => 'minimalizr',
					'settings' => $setting_id,
					'type'     => 'text',
				]
			);
		}
	}

	public static function header_output(): void {
		$css = [];

		$css[] = self::compile_media_query(
			'@media (min-width: 1px) and (max-width: 1024px)',
			[
				[ '.minimal-navigator', 'background-color', 'sidebarBg' ],
				[ '.minimal-top-menu > li > a', 'color', 'sidebarFont' ],
			]
		);
		$css[] = self::compile_media_query(
			'@media (min-width: 1025px)',
			[
				[ '.minimal-top-menu > li > a', 'color', 'topFont' ],
			]
		);

		$rules = [
			[ '#content a:not(.pure-button), #content a:visited:not(.pure-button), #content .linkcolor', 'color', 'link_textcolor' ],
			[ '#content', 'color', 'contentFont' ],
			[ '#minimal-header', 'background-color', 'topBg' ],
			[ '#minimal-header .site-title > a, #minimal-header .site-title > a:visited, #minimal-header', 'color', 'topFont' ],
			[ '.minimal-top-menu > li.dropdown > ul.dropdown-menu li', 'background-color', 'sidebarBg' ],
			[ '.minimal-top-menu > li.dropdown > ul.dropdown-menu, .minimal-top-menu > li.dropdown > ul.dropdown-menu li > a', 'color', 'sidebarFont' ],
			[ '#footer', 'background-color', 'footerBg' ],
			[ '#footer', 'color', 'footerFont' ],
			[ '#footer a:not(.pure-button)', 'color', 'footerLink' ],
			[ '#minimal-wrapper form', 'background-color', 'formBg' ],
			[ '#minimal-wrapper form', 'color', 'formFont' ],
			[ self::input_selector(), 'background-color', 'inputBg' ],
			[ self::input_selector(), 'color', 'inputFont' ],
			[ self::input_selector(), 'border-color', 'inputBorder' ],
			[ '.minimal-box', 'background-color', 'minimalizr_minimal_box_background_color' ],
			[ '.minimal-box, h2.minimal-box, h3.minimal-box', 'margin-bottom', 'minimalizr_minimal_box_margin_bottom', '', 'px' ],
			[ '.minimal-box > .container > h1', 'color', 'minimalizr_minimal_box_color' ],
			[ '.minimal-box > .container > h2', 'color', 'minimalizr_minimal_box_color' ],
			[ '.minimal-box > .container > p', 'color', 'minimalizr_minimal_box_color' ],
			[ '.minimal-site-alert', 'background-color', 'minimalizr_minimal_site_alert_background_color' ],
			[ '.minimal-site-alert', 'color', 'minimalizr_minimal_site_alert_color' ],
			[ '.minimal-site-alert > .container > a', 'color', 'minimalizr_minimal_site_alert_color' ],
			[ '.minimal-footer-alert', 'background-color', 'minimalizr_minimal_footer_alert_background_color' ],
			[ '.minimal-footer-alert', 'color', 'minimalizr_minimal_footer_alert_color' ],
			[ '.minimal-footer-alert > .container > a', 'color', 'minimalizr_minimal_footer_alert_color' ],
		];

		$css[] = self::compile_rules( $rules );
		$css = array_filter( $css );

		if ( empty( $css ) ) {
			return;
		}

		echo "<style type=\"text/css\">\n" . implode( "\n", $css ) . "\n</style>\n";
	}

	public static function live_preview(): void {
		$script_path = get_theme_file_path( 'js/customizer.js' );
		$script_version = file_exists( $script_path )
			? (string) filemtime( $script_path )
			: ( defined( 'MINIMALIZR_VERSION' ) ? MINIMALIZR_VERSION : false );

		wp_enqueue_script(
			'mytheme-themecustomizer',
			get_theme_file_uri( 'js/customizer.js' ),
			[ 'customize-preview', 'jquery' ],
			$script_version,
			[
				'in_footer' => true,
			]
		);
	}

	public static function generate_css(
		string $selector,
		string $style,
		string $mod_name,
		string $prefix = '',
		string $postfix = '',
		bool $echo = true
	): string {
		$mod = get_theme_mod( $mod_name, '' );

		if ( '' === $mod || null === $mod || false === $mod ) {
			return '';
		}

		$return = sprintf( '%s { %s:%s; }', $selector, $style, $prefix . $mod . $postfix );

		if ( $echo ) {
			echo $return;
		}

		return $return;
	}

	private static function register_existing_colors( WP_Customize_Manager $wp_customize ): void {
		$settings = [
			'contentFont'    => [ __( 'Content Font', 'minimalizr' ), '#444444' ],
			'link_textcolor' => [ __( 'Links', 'minimalizr' ), '#2889c1' ],
			'topBg'          => [ __( 'Top Background', 'minimalizr' ), '#ffffff' ],
			'topFont'        => [ __( 'Top Font', 'minimalizr' ), '#444444' ],
			'sidebarBg'      => [ __( 'Sub-Menu/Sidebar Background', 'minimalizr' ), '#444444' ],
			'sidebarFont'    => [ __( 'Sub-Menu/Sidebar Font', 'minimalizr' ), '#ffffff' ],
			'footerBg'       => [ __( 'Footer Background', 'minimalizr' ), '#f7f7f7' ],
			'footerFont'     => [ __( 'Footer Font', 'minimalizr' ), '#444444' ],
			'footerLink'     => [ __( 'Footer Link', 'minimalizr' ), '#2889c1' ],
			'formBg'         => [ __( 'Forms Background', 'minimalizr' ), '#eeeeee' ],
			'formFont'       => [ __( 'Forms Font', 'minimalizr' ), '#444444' ],
			'inputBg'        => [ __( 'Input Background', 'minimalizr' ), '#ffffff' ],
			'inputFont'      => [ __( 'Input Font', 'minimalizr' ), '#444444' ],
			'inputBorder'    => [ __( 'Input Border', 'minimalizr' ), '#888888' ],
		];

		foreach ( $settings as $setting_id => [ $label, $default ] ) {
			self::add_color_setting( $wp_customize, $setting_id, 'colors', $label, $default, 'minimalizr-' . $setting_id );
		}
	}

	private static function register_minimal_box( WP_Customize_Manager $wp_customize ): void {
		$wp_customize->add_section(
			'minimalizr_minimal_box',
			[
				'title'    => __( 'Minimal Box', 'minimalizr' ),
				'priority' => 36,
			]
		);

		self::add_color_setting(
			$wp_customize,
			'minimalizr_minimal_box_background_color',
			'minimalizr_minimal_box',
			__( 'Background Color', 'minimalizr' ),
			'#dddddd'
		);
		self::add_color_setting(
			$wp_customize,
			'minimalizr_minimal_box_color',
			'minimalizr_minimal_box',
			__( 'Text Color', 'minimalizr' ),
			'#000000'
		);

		$wp_customize->add_setting(
			'minimalizr_minimal_box_margin_bottom',
			[
				'type'              => 'theme_mod',
				'default'           => 40,
				'capability'        => 'edit_theme_options',
				'transport'         => 'postMessage',
				'sanitize_callback' => [ self::class, 'sanitize_margin_bottom' ],
			]
		);

		$wp_customize->add_control(
			'minimalizr_minimal_box_margin_bottom',
			[
				'label'       => __( 'Bottom Margin (px)', 'minimalizr' ),
				'section'     => 'minimalizr_minimal_box',
				'settings'    => 'minimalizr_minimal_box_margin_bottom',
				'type'        => 'number',
				'input_attrs' => [
					'min'  => 0,
					'max'  => 100,
					'step' => 1,
				],
			]
		);
	}

	public static function sanitize_margin_bottom( string $value ): int {
		$value = filter_var( $value, FILTER_VALIDATE_INT );

		if ( false === $value ) {
			return 40;
		}

		return max( 0, min( 100, $value ) );
	}

	private static function register_alert_sections( WP_Customize_Manager $wp_customize ): void {
		$sections = [
			'minimalizr_minimal_site_alert' => [
				__( 'Site Alert', 'minimalizr' ),
				'minimalizr_minimal_site_alert_background_color',
				'minimalizr_minimal_site_alert_color',
			],
			'minimalizr_minimal_footer_alert' => [
				__( 'Footer Alert', 'minimalizr' ),
				'minimalizr_minimal_footer_alert_background_color',
				'minimalizr_minimal_footer_alert_color',
			],
		];

		$priority = 37;
		foreach ( $sections as $section_id => [ $title, $background_setting, $color_setting ] ) {
			$wp_customize->add_section(
				$section_id,
				[
					'title'    => $title,
					'priority' => $priority++,
				]
			);

			self::add_color_setting(
				$wp_customize,
				$background_setting,
				$section_id,
				__( 'Background Color', 'minimalizr' ),
				'#cccccc'
			);
			self::add_color_setting(
				$wp_customize,
				$color_setting,
				$section_id,
				__( 'Text Color', 'minimalizr' ),
				'#000000'
			);
		}
	}

	private static function add_color_setting(
		WP_Customize_Manager $wp_customize,
		string $setting_id,
		string $section_id,
		string $label,
		string $default,
		?string $control_id = null
	): void {
		$wp_customize->add_setting(
			$setting_id,
			[
				'type'              => 'theme_mod',
				'default'           => $default,
				'capability'        => 'edit_theme_options',
				'transport'         => 'postMessage',
				'sanitize_callback' => 'sanitize_hex_color',
			]
		);

		$wp_customize->add_control(
			new WP_Customize_Color_Control(
				$wp_customize,
				$control_id ?? $setting_id,
				[
					'label'    => $label,
					'section'  => $section_id,
					'settings' => $setting_id,
				]
			)
		);
	}

	private static function set_post_message_settings( WP_Customize_Manager $wp_customize ): void {
		foreach ( [ 'blogname', 'background_color' ] as $setting_id ) {
			$setting = $wp_customize->get_setting( $setting_id );
			if ( $setting ) {
				$setting->transport = 'postMessage';
			}
		}
	}

	private static function compile_media_query( string $media_query, array $rules ): string {
		$compiled = self::compile_rules( $rules );

		return $compiled ? $media_query . " {\n" . $compiled . "\n}" : '';
	}

	private static function compile_rules( array $rules ): string {
		$compiled = [];

		foreach ( $rules as $rule_definition ) {
			[ $selector, $style, $setting_id, $prefix, $postfix ] = array_pad( $rule_definition, 5, '' );
			$rule = self::generate_css( $selector, $style, $setting_id, $prefix, $postfix, false );
			if ( $rule ) {
				$compiled[] = $rule;
			}
		}

		return implode( "\n", $compiled );
	}

	private static function input_selector(): string {
		return 'input[type=text],input[type=password],input[type=email],input[type=url],input[type=date],input[type=month],input[type=time],input[type=datetime],input[type=datetime-local],input[type=week],input[type=number],input[type=search],input[type=tel],input[type=color],select,textarea';
	}
}

add_action( 'customize_register', [ 'MyTheme_Customize', 'register' ] );
add_action( 'wp_head', [ 'MyTheme_Customize', 'header_output' ] );
add_action( 'customize_preview_init', [ 'MyTheme_Customize', 'live_preview' ] );
