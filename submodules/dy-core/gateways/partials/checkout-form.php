<?php
if (!defined('WPINC')) exit;
$has_gateway = (bool) $view['has_gateway'];
$hide_form = $has_gateway ? 'class="hidden"' : '';
$submit_form = $view['submit_label'];
$header_form = $view['title'];
$autocomplete_scope = $has_gateway ? 'section-payment billing' : 'section-contact';
$site_key = get_turnstile_site_key();
?>
<form id="<?php echo esc_attr($view['id']); ?>" data-dy-checkout <?php echo $hide_form;?>  data-method="post" data-action="<?php echo esc_attr(base64_encode($view['action'])); ?>">

	    <div class="text-center bottom-20" id="dy_checkout_branding">
			<p class="large text-muted">
				<?php echo esc_html(__($header_form)); ?>
			</p>
		</div>
		
		<hr />


		<?php if($has_gateway) : ?>
			<div id="dy_crypto_form" class="hidden small">
				<?php Dy_Checkout_Form::crypto(); ?>
				<hr />
			</div>
		<?php endif; ?>
	
        <input type="hidden" name="tx_id" value="" />
        <?php foreach ($view['fields'] as $name => $value): ?>
            <input type="hidden" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($value); ?>" />
        <?php endforeach; ?>

		<div>
			<h3><?php echo esc_html(__('Contact Details', 'dycore')); ?></h3>
			<div class="pure-g gutters">
				<div class="pure-u-1 pure-u-md-1-2">
					<label for="first_name"><?php echo esc_html(__('Name', 'dycore')); ?></label>
					<input
						type="text"
						name="first_name"
						id="first_name"
						class="bottom-20 required"
						autocomplete="<?php echo esc_attr($autocomplete_scope . ' given-name'); ?>"
						autocapitalize="words"
						required
					>
				</div>
				<div class="pure-u-1 pure-u-md-1-2">
					<label for="lastname"><?php echo esc_html(__('Last Name', 'dycore')); ?></label>
					<input
						type="text"
						name="lastname"
						id="lastname"
						class="bottom-20 required"
						autocomplete="<?php echo esc_attr($autocomplete_scope . ' family-name'); ?>"
						autocapitalize="words"
						required
					>
				</div>
			</div>
			<div class="pure-g gutters">
				<div class="pure-u-1 pure-u-md-1-2">
					<label for="email"><?php echo esc_html(__('Email', 'dycore')); ?></label>
					<input
						type="email"
						name="email"
						id="email"
						class="bottom-20 required"
						autocomplete="<?php echo esc_attr($autocomplete_scope . ' email'); ?>"
						autocapitalize="none"
						autocorrect="off"
						spellcheck="false"
						required
					>				
				</div>
				<div class="pure-u-1 pure-u-md-1-2">
						<label for="repeat_email"><?php echo esc_html(__('Repeat Email', 'dycore')); ?></label>
						<input
							type="email"
							name="repeat_email"
							id="repeat_email"
							class="bottom-20 required"
							autocomplete="off"
							autocapitalize="none"
							autocorrect="off"
							spellcheck="false"
							required
						>
				</div>
			</div>
			
			<div class="pure-g gutters">
				<div class="pure-u-1 pure-u-md-1-2">
					<div class="bottom-20">
						<label for="phone"><?php echo esc_html(__('Phone', 'dycore')); ?></label>
						<div class="pure-g">
							<div class="pure-u-1-2">
								<select
									name="country_calling_code"
									id="country_calling_code"
									class="countryCallingCode required"
									autocomplete="<?php echo esc_attr($autocomplete_scope . ' tel-country-code'); ?>"
									required
									>
									<option value="">--</option>
								</select>
							</div>
							<div class="pure-u-1-2">
								<input
									type="tel"
									inputmode="tel"
									name="phone"
									id="phone"
									class="required"
									autocomplete="<?php echo esc_attr($autocomplete_scope . ' tel-national'); ?>"
									aria-label="<?php echo esc_attr__('Phone Number', 'dycore'); ?>"
									required
								>
							</div>
						</div>
					</div>
				</div>							
			</div>	
			
		</div>

		<div id="dy_card_payment_conditions" class="hidden small">
			<?php echo $view['card_notice']; ?>
		</div>
		
		<?php if($has_gateway) : ?>
			<?php Dy_Checkout_Form::card(); ?>
		<?php endif; ?>

		<?php echo $view['terms']; ?>
		
		<?php echo $view['inquiry']; ?>
		
		<?php if($site_key !== ''): ?>
			<div class="dy-turnstile-submit">

				<div class="bottom-20">
					<div id="turnstile-container-1"></div>
					<div id="turnstile-container-2"></div>
				</div>


				<button
					type="button"
					onClick="checkoutFormSubmit(this.form); return false;"
					class="pure-button pure-button-primary strong large">
					<?php echo esc_html(__($submit_form)); ?>
				</button>
			</div>
<?php endif; ?>
</form>
