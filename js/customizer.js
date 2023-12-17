/**
 * Theme Customizer enhancements for a better user experience.
 *
 * Contains handlers to make Theme Customizer preview reload changes asynchronously.
 */

 jQuery(() => {

	// Update the site title in real time...
	wp.customize( 'blogname', value => {
		value.bind( newval => {
			jQuery( '.site-title a' ).text( newval );
		} );
	} );
	
	//Update site background color...
	wp.customize( 'background_color', value => {
		value.bind( newval => {
			jQuery('.custom-background').css('background-color', newval );
		} );
	} );
	
	//Update content color...
	wp.customize( 'contentFont', value => {
		value.bind( newval => {
			jQuery('#content').css('color', newval );
		} );
	} );	
	
	//Update site link color in real time...
	wp.customize( 'link_textcolor', value => {
		value.bind( newval => {
			jQuery('a:not(.btn), a:visited:not(.btn), .linkcolor').css('color', newval );
			jQuery('.entry-footer > span:not(.tags-links):hover').css('background-color', newval );
		} );
	} );
	
	wp.customize( 'topFont', value => {
		value.bind( newval => {
			jQuery('#minimal-header, .minimal-top-menu > li > a, .minimal-top-menu > li > a:visited').css('color', newval );
		} );
	} );
	
	wp.customize( 'sidebarFont', value => {
		value.bind( newval => {
			jQuery('#minimal-top-menu.minimal-top-menu > li.dropdown > ul.dropdown-menu a, body.toggled .minimal-navigator a').css('color', newval );
		} );
	} );
	
	wp.customize( 'topBg', value => {
		value.bind( newval => {
			jQuery('#minimal-header').css('background-color', newval );
		} );
	} );

	wp.customize( 'sidebarBg', value => {
		value.bind( newval => {
			jQuery('#minimal-top-menu.minimal-top-menu > li.dropdown > ul.dropdown-menu li, body.toggled .minimal-navigator').css('background-color', newval );
		} );
	} );
	
	wp.customize( 'footerBg', value => {
		value.bind( newval => {
			jQuery('#footer').css('background-color', newval );
		} );
	} );	


	wp.customize( 'footerFont', value => {
		value.bind( newval => {
			jQuery('#footer').css('color', newval );
		} );
	} );		

	wp.customize( 'footerLink', value => {
		value.bind( newval => {
			jQuery('#footer a:not(.pure-button)').css('color', newval );
		} );
	} );

	//form
	wp.customize( 'formBg', value => {
		value.bind( newval => {
			jQuery("#minimal-wrapper").find("form").css('background-color', newval);
		} );
	} );

	wp.customize( 'formFont', value => {
		value.bind( newval => {
			jQuery('#minimal-wrapper form').css({'color': newval});
		} );
	} );	

	//input background
	wp.customize( 'inputBg', value => {
		value.bind( newval => {
			jQuery('input[type=text],input[type=password],input[type=email],input[type=url],input[type=date],input[type=month],input[type=time],input[type=datetime],input[type=datetime-local],input[type=week],input[type=number],input[type=search],input[type=tel],input[type=color],select,textarea, input[type=text], select').css('background-color', newval );
		} );
	} );

	//input fontFont
	wp.customize( 'inputFont', value => {
		value.bind( newval => {
			jQuery('input[type=text],input[type=password],input[type=email],input[type=url],input[type=date],input[type=month],input[type=time],input[type=datetime],input[type=datetime-local],input[type=week],input[type=number],input[type=search],input[type=tel],input[type=color],select,textarea, input[type=text], select').css({'color': newval});
		} );
	} );

	//input border
	wp.customize( 'inputBorder', value => {
		value.bind( newval => {
			jQuery('input[type=text],input[type=password],input[type=email],input[type=url],input[type=date],input[type=month],input[type=time],input[type=datetime],input[type=datetime-local],input[type=week],input[type=number],input[type=search],input[type=tel],input[type=color],select,textarea, input[type=text], select').css({'border-color': newval});
		} );
	} );		

 });