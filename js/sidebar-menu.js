$(document).ready(function() {

	"use strict";
	change_topBG();
	
    $('[data-toggle="offcanvas"]').click(function() {
 
        $('body').toggleClass('toggled');
		is_overlay_visible();
    });

	$(".top_menu > li.dropdown > a").click(function(e){
		e.preventDefault();
	});
	$('.top_menu > li.dropdown').click(function(e){
		$('.top_menu > li.dropdown').not(this).removeClass('toggled');
		$(this).find('.dropdown-menu').toggleClass('toggled');
	}).mouseover(function(){
		if($(window).width() >= 1024)
		{
			$('.top_menu > li.dropdown').not(this).removeClass('toggled');
			$(this).find('.dropdown-menu').addClass('toggled');
		}
	}).mouseleave(function(e) {
		if($(window).width() >= 1024)
		{
			$('.top_menu > li.dropdown').not(this).removeClass('toggled');
			$(this).find('.dropdown-menu').removeClass('toggled');
		}
    });
	
	var overflow_x = $('<div></div>').addClass('overflow_x');
	$('table').each(function(){
		$(this).wrap(overflow_x);
	});

    function is_overlay_visible() {
        $('.overlay').toggle();
        if ($('body').hasClass('toggled')) 
		{
            $('.minimal-menu-bar > i').toggleClass('fa-bars fa-times');
			console.log('menu opened');
        } else
		{
            $('.minimal-menu-bar > i').toggleClass('fa-times fa-bars');
			console.log('menu closed');
        }
    }
	
	//fix beaver form bg 
	beaver_fullbg_forms();
	
	//scroll
	beaver_scroll_down();
	
});


$(window).on("load resize", function(){

	//fix sidebar height bug
	$('.top_navigator').each(function(){
		if($(this).parent().hasClass('minimal') || ($(this).parent().hasClass('responsive') && $(window).width() < 1024))
		{
			$(this).attr( "style", "height: 100vh;" );
		}
		else
		{
			$(this).removeAttr('style');
		}
	});
		
});

function beaver_fullbg_forms()
{
	if($(".fl-row-bg-photo").find("form").length)
	{
		$(".fl-row-bg-photo").find("form").each(function(){
			var formbg = $(this).css("background-color");
			var formtransparent = formbg.replace(/\)/g, ", 0.7)");
			formtransparent = formtransparent.replace(/rgb/g, "rgba");
			$(this).css({"background-color": formtransparent});
			
		});		
	}
}

function beaver_scroll_down()
{
	if(!$('html').hasClass('.fl-builder-edit'))
	{
		$('.fl-row-full-height').each(function(){
			var this_container = $(this);
			var sdbutton = $('<span>').attr({'class': 'scroll-down pointer'}).html('<i class="fa fa-chevron-down"></i>');
			$(this).find('.fl-row-content-wrap').append(sdbutton);
			sdbutton.click(function(){
				var sheight = this_container.height();
				var topoffset = this_container.offset().top;
				if(this_container.next().hasClass('fl-row-full-height'))
				{
					
					$("html, body").animate({ scrollTop: (sheight+topoffset) }, "slow");
				}
				else
				{
					sheight = sheight-$('.minimal-header').height();
					$("html, body").animate({ scrollTop: (sheight+topoffset) }, "slow");
				}
			});
		});			
	}
}

function change_topBG()
{
	if($('.visible-menu').length)
	{
		
	}
	else
	{
		if($(window).scrollTop() > $('.minimal-header').height())
		{
			$('.minimal-header').addClass('visible-menu');
		}
		
		$(window).scroll(function(){
			
			 var scroll_pos = $(this).scrollTop();
			 
			 if(scroll_pos > $('.minimal-header').height())
			 {
				 $('.minimal-header').addClass('visible-menu');
			 }
			 else
			 {
				 $('.minimal-header').removeClass('visible-menu');
			 }
		});		
	}

}