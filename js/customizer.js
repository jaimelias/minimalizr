/**
 * Theme Customizer live-preview bindings.
 */

jQuery( () => {
	const $ = jQuery;
	const customize = wp.customize;
	const inputSelector = 'input[type=text],input[type=password],input[type=email],input[type=url],input[type=date],input[type=month],input[type=time],input[type=datetime],input[type=datetime-local],input[type=week],input[type=number],input[type=search],input[type=tel],input[type=color],select,textarea';
	const isDesktop = () => window.matchMedia( '(min-width: 1025px)' ).matches;

	let topFontValue = '';
	let sidebarFontValue = '';
	let sidebarBackgroundValue = '';

	const bindStyle = ( settingId, selector, property, formatValue = value => value ) => {
		customize( settingId, setting => {
			setting.bind( value => {
				$( selector ).css( property, formatValue( value ) );
			} );
		} );
	};

	const applyTopFont = () => {
		if ( ! topFontValue ) {
			return;
		}

		$( '#minimal-header, #minimal-header .site-title > a' ).css( 'color', topFontValue );
		$( '.minimal-top-menu > li > a' ).css( 'color', isDesktop() ? topFontValue : '' );
	};

	const applySidebarFont = () => {
		if ( ! sidebarFontValue ) {
			return;
		}

		if ( ! isDesktop() ) {
			$( '.minimal-navigator a' ).css( 'color', sidebarFontValue );
		}
		$( '.minimal-top-menu > li.dropdown > ul.dropdown-menu li > a' ).css( 'color', sidebarFontValue );
	};

	const applySidebarBackground = () => {
		if ( ! sidebarBackgroundValue ) {
			return;
		}

		$( '.minimal-navigator' ).css( 'background-color', isDesktop() ? '' : sidebarBackgroundValue );
		$( '.minimal-top-menu > li.dropdown > ul.dropdown-menu li' ).css( 'background-color', sidebarBackgroundValue );
	};

	$( window ).on( 'resize', () => {
		applyTopFont();
		applySidebarFont();
		applySidebarBackground();
	} );

	customize( 'blogname', setting => {
		setting.bind( value => {
			const $siteTitle = $( '.site-title a' );

			if ( ! $siteTitle.find( 'img' ).length ) {
				$siteTitle.text( value );
			}
		} );
	} );

	[
		[ 'background_color', '.custom-background', 'background-color' ],
		[ 'contentFont', '#content', 'color' ],
		[ 'link_textcolor', '#content a:not(.pure-button), #content a:visited:not(.pure-button), #content .linkcolor', 'color' ],
		[ 'topBg', '#minimal-header', 'background-color' ],
		[ 'footerBg', '#footer', 'background-color' ],
		[ 'footerFont', '#footer', 'color' ],
		[ 'footerLink', '#footer a:not(.pure-button)', 'color' ],
		[ 'formBg', '#minimal-wrapper form', 'background-color' ],
		[ 'formFont', '#minimal-wrapper form', 'color' ],
		[ 'inputBg', inputSelector, 'background-color' ],
		[ 'inputFont', inputSelector, 'color' ],
		[ 'inputBorder', inputSelector, 'border-color' ],
		[ 'minimalizr_minimal_box_background_color', '.minimal-box', 'background-color' ],
		[ 'minimalizr_minimal_box_margin_bottom', '.minimal-box, h2.minimal-box, h3.minimal-box', 'margin-bottom', value => `${value}px` ],
		[ 'minimalizr_minimal_box_color', '.minimal-box > .container > h1, .minimal-box > .container > h2, .minimal-box > .container > p', 'color' ],
		[ 'minimalizr_minimal_site_alert_background_color', '.minimal-site-alert', 'background-color' ],
		[ 'minimalizr_minimal_site_alert_color', '.minimal-site-alert, .minimal-site-alert > .container > a', 'color' ],
		[ 'minimalizr_minimal_footer_alert_background_color', '.minimal-footer-alert', 'background-color' ],
		[ 'minimalizr_minimal_footer_alert_color', '.minimal-footer-alert, .minimal-footer-alert > .container > a', 'color' ],
	].forEach( ( [ settingId, selector, property, formatValue ] ) => {
		bindStyle( settingId, selector, property, formatValue );
	} );

	customize( 'topFont', setting => {
		setting.bind( value => {
			topFontValue = value;
			applyTopFont();
			applySidebarFont();
		} );
	} );

	customize( 'sidebarFont', setting => {
		setting.bind( value => {
			sidebarFontValue = value;
			applySidebarFont();
		} );
	} );

	customize( 'sidebarBg', setting => {
		setting.bind( value => {
			sidebarBackgroundValue = value;
			applySidebarBackground();
		} );
	} );
} );
