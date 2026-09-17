jQuery(() => {
    selectGateway();
    jQuery('[data-dy-checkout]').on('submit', function (event) {
        event.preventDefault();
        checkoutFormSubmit(this);
    });
});

const selectGateway = () => {
	
	const thisForm = jQuery('[data-dy-checkout]').first();
	const cardRequiredFields = ['country', 'city', 'address', 'CCNum', 'ExpMonth', 'ExpYear', 'CVV2'];

	const buttons = jQuery('#dy_payment_buttons').find('button');

	buttons.each(function(){

		jQuery(this).click(function(){

			const thisButton = jQuery(this);
			const id = thisButton.attr('data-id');
			const type = thisButton.attr('data-type');
			const branding = thisButton.attr('data-branding');
			let networks = thisButton.attr('data-networks') || '';
			const cryptoForm = jQuery('#dy_crypto_form');
			const networkSelect = cryptoForm.find('select[name="dy_network"]');

			networkSelect.removeClass('required').html('<option value="" selected>--</option>');

			jQuery('#dy_crypto_alert').addClass('hidden');

			if(type === 'card-on-site')
			{
				jQuery('#dy_card_payment_conditions').removeClass('hidden');
				jQuery('.dy_card_form_fields').removeClass('hidden');

				cardRequiredFields.forEach(name => {
					thisForm.find('[name="'+name+'"]').addClass('required').prop('disabled', false);
				});
			}
			else
			{
				if(type === 'card-off-site')
				{
					jQuery('#dy_card_payment_conditions').removeClass('hidden');
				}
				else 
				{
					jQuery('#dy_card_payment_conditions').addClass('hidden');
				}
				
				jQuery('.dy_card_form_fields').addClass('hidden');

				cardRequiredFields.forEach(name => {
					jQuery(thisForm).find('[name="'+name+'"]').removeClass('required').removeClass('invalid_field').prop('disabled', true);
				});
			}

			if(type === 'crypto')
			{
				
				networkSelect.addClass('required').prop('disabled', false);
				cryptoForm.removeClass('hidden');
				networks = JSON.parse(networks);

				for (let k in networks) 
				{
					const options = jQuery('<option></option>').attr({'value': k}).html(networks[k].name);
					jQuery(networkSelect).append(options);
				}

				setTimeout(()=>{
					networkSelect.focus();
				}, 200)

				networkSelect.off('change.dyCheckout').on('change.dyCheckout', function(){
					const thisField = jQuery(this);
					const value = thisField.val();
					const text = thisField.find('option:selected').text();

					jQuery('#dy_crypto_network_code').text(value.toUpperCase());
					jQuery('#dy_crypto_network_name').text(text);
					jQuery('#dy_crypto_alert').removeClass('hidden');
				});

			}
			else
			{
				thisForm.find('input[name="first_name"]').focus();
				jQuery('#dy_crypto_form').addClass('hidden');
			}

			jQuery('#dy_checkout_branding').html(branding);
			thisForm.removeClass('hidden');
			thisForm.find('input[name="dy_request"]').val(id);
			const intent = thisButton.attr('data-intent') || 'payment';
			thisForm.find('[name="intent"]').val(intent);
			thisForm.find('[name="gateway_id"]').val(intent === 'payment' ? id : '');
		});
	});


	//shows form if there is only one button
	if(buttons.length === 1)
	{
		buttons.trigger('click');
		jQuery('#dy_payment_buttons').hide();
	}


};

const checkoutFormSubmit = async element => {
	const thisForm = jQuery(element || '[data-dy-checkout]');
	const { submit_error, correct_form } = dyCheckoutArgs;

	const invalids = [];
	const formFields = formToArray(thisForm);

	formFields.forEach(({ name, value }) => {
		const field = thisForm.find(`[name="${name}"]`);
		const label = thisForm.find(`label[for="${name}"]`);

		if (!field.hasClass('required')) return;

		if (!value || !isValidValue({ name, value, thisForm })) {
			invalids.push(name);

			if (name.startsWith('terms_conditions_')) {
				label.addClass('invalid_checkmark');
			} else {
				field.addClass('invalid_field');
			}
		} else if (name.startsWith('terms_conditions_')) {
			label.removeClass('invalid_checkmark');
		} else {
			field.removeClass('invalid_field');
		}
	});

	if (invalids.length > 0) {
		if (invalids.includes('country') || invalids.includes('country_calling_code')) {
			countryDropdown();
		}

		const errorMessage = `${submit_error}: ${invalids.join(', ')}`;

		dyAlert(errorMessage, correct_form);
		return false;
	}

    if (thisForm.data('submitting')) return false;
    thisForm.data('submitting', true);
    try {
        const pending = [];
        thisForm[0].dispatchEvent(new CustomEvent('dy:checkout:prepare', {
            bubbles: true, detail: { waitUntil: promise => pending.push(promise) }
        }));
        await Promise.all(pending);
        thisForm[0].dispatchEvent(new CustomEvent('dy:checkout:submit', { bubbles: true }));
        await createFormSubmit(thisForm);
    } catch (error) {
        dyAlert(error.message || dyCheckoutArgs.submit_error, dyCheckoutArgs.correct_form);
    } finally {
        thisForm.data('submitting', false);
    }
    return false;
};
