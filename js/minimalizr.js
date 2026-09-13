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

/**
 * Displays a modal alert and resolves when the alert is closed.
 *
 * @param {*} message Alert message displayed as plain text.
 * @param {string|false|null} button Button label, or a falsy value to hide it.
 * @param {Function|null} btnCallback Callback executed when the button closes the alert.
 * @returns {Promise<void>} Resolves when the alert begins closing.
 */
const dyAlert = (message = 'ERROR', button = 'OK', btnCallback = null) => {
	return new Promise((resolve) => {
		const $overlay = jQuery('.alert-overlay');
		const $previousFocus = jQuery(document.activeElement);

		const $modal = jQuery(`
			<div
				class="dy-alert"
				role="alertdialog"
				aria-modal="true"
				aria-label="Alert"
				tabindex="-1"
			>
				<p class="dy-alert__message"></p>
			</div>
		`);

		$modal.find('.dy-alert__message').text(message);

		if (button && typeof button === 'string') {
			const $button = jQuery('<button>', {
				type: 'button',
				class: 'pure-button rounded',
			});

			$button
				.append(
					jQuery('<span>', {
						class: 'dashicons dashicons-yes-alt',
					})
				)
				.append(document.createTextNode(` ${button}`));

			$modal.append($button);
		}

		let closed = false;

		const close = () => {
			if (closed) {
				return;
			}

			closed = true;

			$overlay.off('click', close);

			$modal.removeClass('dy-alert--visible');
			$overlay.removeClass('alert-overlay--visible');

			setTimeout(() => $modal.remove(), 150);

			$previousFocus.trigger('focus');

			resolve();
		};

		const buttonClose = () => {
			close();

			if (typeof btnCallback === 'function') {
				btnCallback();
			}
		};

		$modal.find('.pure-button').one('click', buttonClose);
		$overlay.one('click', close);

		jQuery('body').append($modal);

		$overlay.addClass('alert-overlay--visible');

		requestAnimationFrame(() => {
			$modal.addClass('dy-alert--visible');
		});

		const $button = $modal.find('.pure-button');

		($button.length ? $button : $modal).trigger('focus');
	});
};