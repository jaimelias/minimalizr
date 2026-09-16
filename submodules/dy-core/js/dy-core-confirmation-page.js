jQuery(() => {
	const { textCopiedToClipboard } = dyConfirmationArgs;

	jQuery('.copyToClipboard').each(function () {
		const element = jQuery(this);

		element.addClass('relative');
		element.wrapInner("<div class='copy-to-clipboard-target'></div>");
		element.append(
			'<span class="hidden absolute copy-to-clipboard-notification" '
			+ 'style="padding:10px;background-color:#000;color:#fff;left:0;top:0;right:0;bottom:0;">'
			+ textCopiedToClipboard
			+ '</span>'
		);

		element.on('click', function () {
			const clicked = jQuery(this);
			const notification = clicked.find('.copy-to-clipboard-notification');

			notification.removeClass('hidden');
			navigator.clipboard.writeText(
				clicked.find('.copy-to-clipboard-target').text()
			);

			setTimeout(() => notification.addClass('hidden'), 1500);
		});
	});
});
