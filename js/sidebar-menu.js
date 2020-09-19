jQuery(document).ready(function() {

	'use strict';
	
    jQuery('[data-toggle="offcanvas"]').click(function() {
 
        jQuery('body').toggleClass('toggled');
		is_overlay_visible();
    });
	
	jQuery('.overlay').click(function(){
		jQuery('body').toggleClass('toggled');
		is_overlay_visible();
	});

	jQuery('.top_menu > li.dropdown > a > .caret').click(function(e){
		e.preventDefault();
	});
	
	jQuery('.top_menu > li.dropdown').click(function(e){
		jQuery('.top_menu > li.dropdown').not(this).find('.dropdown-menu').removeClass('toggled');
		jQuery(this).find('.dropdown-menu').toggleClass('toggled');
	}).mouseover(function(){
		if(jQuery(window).width() >= 1024)
		{
			jQuery('.top_menu > li.dropdown').not(this).find('.dropdown-menu').removeClass('toggled');
			jQuery(this).find('.dropdown-menu').addClass('toggled');
		}
	}).mouseleave(function(e) {
		if(jQuery(window).width() >= 1024)
		{
			jQuery('.top_menu > li.dropdown').not(this).find('.dropdown-menu').removeClass('toggled');
			jQuery(this).find('.dropdown-menu').removeClass('toggled');
		}
    });

    function is_overlay_visible() {
        jQuery('.overlay').toggle();
        if (jQuery('body').hasClass('toggled')) 
		{
            jQuery('.minimal-menu-bar > i').toggleClass('fa-bars fa-times');
			console.log('menu opened');
        } else
		{
            jQuery('.minimal-menu-bar > i').toggleClass('fa-times fa-bars');
			console.log('menu closed');
        }
    }
	
	//fix beaver form bg 
	beaver_fullbg_forms();
	
	//scroll
	beaver_scroll_down();
	
});


jQuery(window).on('load resize', function(){

	//fix sidebar height bug
	jQuery('.top_navigator').each(function(){
		if(jQuery(this).parent().hasClass('minimal') || (jQuery(this).parent().hasClass('responsive') && jQuery(window).width() < 1024))
		{
			jQuery(this).attr('style', 'height: 100vh;');
		}
		else
		{
			jQuery(this).removeAttr('style');
		}
	});
		
});

function beaver_fullbg_forms()
{
	if(jQuery('.fl-row-bg-photo').find('form').length)
	{
		jQuery('.fl-row-bg-photo').find('form').each(function(){
			var formbg = jQuery(this).css('background-color');
			var formtransparent = formbg.replace(/\)/g, ", 0.7)");
			formtransparent = formtransparent.replace(/rgb/g, "rgba");
			jQuery(this).css({'background-color': formtransparent});
			
		});		
	}
}

function beaver_scroll_down()
{
	if(!jQuery('html').hasClass('.fl-builder-edit'))
	{
		jQuery('.fl-row-full-height').each(function(){
			var this_container = jQuery(this);
			var sdbutton = jQuery('<span>').attr({'class': 'scroll-down pointer'}).html('<i class="fa fa-chevron-down"></i>');
			jQuery(this).find('.fl-row-content-wrap').append(sdbutton);
			sdbutton.click(function(){
				var sheight = this_container.height();
				var topoffset = this_container.offset().top;
				if(this_container.next().hasClass('fl-row-full-height'))
				{
					jQuery('html, body').animate({ scrollTop: (sheight+topoffset) }, 'slow');
				}
				else
				{
					sheight = sheight-jQuery('.minimal-header').height();
					jQuery('html, body').animate({ scrollTop: (sheight+topoffset) }, 'slow');
				}
			});
		});			
	}
}
