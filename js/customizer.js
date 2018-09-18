/**
 * Theme Customizer enhancements for a better user experience.
 *
 * Contains handlers to make Theme Customizer preview reload changes asynchronously.
 */

( function( $ ) {

	
	// Update the site title in real time...
	wp.customize( 'blogname', function( value ) {
		value.bind( function( newval ) {
			$( '.site-title a' ).text( newval );
		} );
	} );
	
	//Update site background color...
	wp.customize( 'background_color', function( value ) {
		value.bind( function( newval ) {
			$('.custom-background').css('background-color', newval );
		} );
	} );
	
	//Update content color...
	wp.customize( 'contentFont', function( value ) {
		value.bind( function( newval ) {
			$('#content').css('color', newval );
		} );
	} );	
	
	//Update site link color in real time...
	wp.customize( 'link_textcolor', function( value ) {
		value.bind( function( newval ) {
			$('a:not(.btn), a:visited:not(.btn), .linkcolor').css('color', newval );
			$('.entry-footer > span:not(.tags-links):hover').css('background-color', newval );
		} );
	} );
	
	wp.customize( 'topFont', function( value ) {
		value.bind( function( newval ) {
			$('.minimal-header, .minimal-header a, .minimal-header a:visited').css('color', newval );
		} );
	} );
	
	wp.customize( 'sidebarFont', function( value ) {
		value.bind( function( newval ) {
			$('#top_menu.top_menu > li.dropdown > ul.dropdown-menu a, .minimal .top_navigator, .minimal .top_navigator a, body.toggled .responsive .top_navigator a').css('color', newval );
		} );
	} );
	
	wp.customize( 'topBg', function( value ) {
		value.bind( function( newval ) {
			$('.minimal-header').css('background-color', newval );
		} );
	} );

	wp.customize( 'sidebarBg', function( value ) {
		value.bind( function( newval ) {
			$('#top_menu.top_menu > li.dropdown > ul.dropdown-menu li, .minimal .top_navigator, body.toggled .responsive .top_navigator').css('background-color', newval );
		} );
	} );
	
	wp.customize( 'footerBg', function( value ) {
		value.bind( function( newval ) {
			$('#footer').css('background-color', newval );
		} );
	} );	


	wp.customize( 'footerFont', function( value ) {
		value.bind( function( newval ) {
			$('#footer').css('color', newval );
		} );
	} );		

	wp.customize( 'footerLink', function( value ) {
		value.bind( function( newval ) {
			$('#footer a:not(.pure-button)').css('color', newval );
		} );
	} );
	//form
	wp.customize( 'formBg', function( value ) {
		value.bind( function( newval ) {
			var patt = /^#([\da-fA-F]{2})([\da-fA-F]{2})([\da-fA-F]{2})$/;
			var matches = patt.exec(newval);
			var rgba = "rgba("+parseInt(matches[1], 16)+","+parseInt(matches[2], 16)+","+parseInt(matches[3], 16)+", 0.8)";
			$("#wrapper").find("form").css('background-color', rgba );
		} );
	} );
	wp.customize( 'formFont', function( value ) {
		value.bind( function( newval ) {
			$('#wrapper form').css({'color': newval});
		} );
	} );	

	//input background
	wp.customize( 'inputBg', function( value ) {
		value.bind( function( newval ) {
			$('input[type=text],input[type=password],input[type=email],input[type=url],input[type=date],input[type=month],input[type=time],input[type=datetime],input[type=datetime-local],input[type=week],input[type=number],input[type=search],input[type=tel],input[type=color],select,textarea, input[type=text], select').css('background-color', newval );
		} );
	} );
	//input fontFont
	wp.customize( 'inputFont', function( value ) {
		value.bind( function( newval ) {
			$('input[type=text],input[type=password],input[type=email],input[type=url],input[type=date],input[type=month],input[type=time],input[type=datetime],input[type=datetime-local],input[type=week],input[type=number],input[type=search],input[type=tel],input[type=color],select,textarea, input[type=text], select').css({'color': newval});
		} );
	} );	
	//input border
	wp.customize( 'inputBorder', function( value ) {
		value.bind( function( newval ) {
			$('input[type=text],input[type=password],input[type=email],input[type=url],input[type=date],input[type=month],input[type=time],input[type=datetime],input[type=datetime-local],input[type=week],input[type=number],input[type=search],input[type=tel],input[type=color],select,textarea, input[type=text], select').css({'border-color': newval});
		} );
	} );		
	
} )( jQuery );


