jQuery(() => {

	toggleMinimalModal();
	toggleDropdownMenu();
	handleScrollPaddingTop();
});

const handleScrollPaddingTop = () => {

	const headerHeight = (jQuery('#minimal-header').length > 0) 
		? jQuery('#minimal-header').height() 
		: 0;
	const adminBarHeight = (jQuery('#wpadminbar').length > 0) 
		? jQuery('#wpadminbar').height()
		: 0;

	const padding = headerHeight + adminBarHeight;

	jQuery('html').css({'scroll-padding-top' : `${padding}px`});
};

const toggleMinimalModal = () => {

	jQuery('[data-toggle="offcanvas"], .menu-overlay').click(() => {

		jQuery('body').toggleClass('toggled');
		jQuery('.minimal-menu-bar > .dashicons').toggleClass('dashicons-menu dashicons-no')

		//closes dropdown menu
		jQuery('.minimal-top-menu > li.dropdown').find('.dropdown-menu').addClass('hidden')

	});
};

const toggleDropdownMenu = () => {

	const dropdown = jQuery('.minimal-top-menu > li.dropdown');

	jQuery(dropdown).find('a[data-toggle="dropdown"]').click(e =>{
		e.preventDefault();
	});

	jQuery(dropdown).on('click mouseover mouseleave', function(e) {
		const {type} = e;

		const thisDropdownMenu = jQuery(this).find('.dropdown-menu');
		let hideOther = false;

		if(type === 'click')
		{
			hideOther = true;
			jQuery(thisDropdownMenu).toggleClass('hidden');
		}
		if(jQuery(window).width() >= 1024)
		{
			hideOther = true;
			
			if(type === 'mouseover')
			{
				jQuery(thisDropdownMenu).removeClass('hidden');
			}
			else if(type === 'mouseleave')
			{
				jQuery(thisDropdownMenu).addClass('hidden');
			}
		}

		if(hideOther)
		{
			jQuery(dropdown).not(this).find('.dropdown-menu').addClass('hidden');
		}
	});

};


const dyAlert = (message = '', button = 'OK') => {

	return new Promise((resolve) => {
		const $overlay = jQuery('.alert-overlay');

		const buttonTag = btn => `<button type="button" class="pure-button rounded">${btn}</button>`

		const $modal = jQuery(`
		<div class="dy-alert" role="alertdialog" aria-modal="true">
			<p class="dy-alert__message"></p>
			${button && typeof button === 'string' ? buttonTag(button) : ''}
		</div>
		`);

		$modal.find('.dy-alert__message').text(message);

		const close = () => {
			$modal.removeClass('dy-alert--visible');
			$overlay.removeClass('alert-overlay--visible');
			setTimeout(() => $modal.remove(), 150);
			resolve();
		};

		$modal.find('.dy-alert__ok').on('click', close);
		$overlay.one('click', close);

		jQuery('body').append($modal);
		$overlay.addClass('alert-overlay--visible');

		requestAnimationFrame(() => $modal.addClass('dy-alert--visible'));

		$modal.find('.dy-alert__ok').trigger('focus');
	});
};
