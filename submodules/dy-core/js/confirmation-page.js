jQuery(() => {

	copyToClipboard();

});


const copyToClipboard = () => {

	const {textCopiedToClipBoard} = dyPackageConfirmationArgs;

	const el = jQuery('.copyToClipboard');

	jQuery(el).each(function(){
		const thisEl = jQuery(this);

		jQuery(thisEl).addClass('relative');

		jQuery(thisEl).wrapInner( "<div class='copy-to-clipboard-target'></div>");		

		jQuery(thisEl)
			.append('<span class="hidden absolute copy-to-clipboard-notification" style="padding: 10px; background-color: #000; color: #fff; left: 0; top: 0; right: 0; bottom: 0;">'+textCopiedToClipBoard+'</span>');

		jQuery(thisEl).click(function(){
			const thisClickedEl = jQuery(this);

			jQuery(thisClickedEl).find('.copy-to-clipboard-notification').removeClass('hidden');

			navigator.clipboard.writeText(jQuery(thisClickedEl).find('.copy-to-clipboard-target').text());

			setTimeout(()=> {
				jQuery(thisClickedEl).find('.copy-to-clipboard-notification').addClass('hidden');
			}, 1500);
		});

	});
};