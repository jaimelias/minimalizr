<?php if ( !defined( 'WPINC' ) ) exit; ?>

<div id="dy_cc_form">
	<hr>

	<div class="dy_card_form_fields hidden">
		<h3><?php echo esc_html__('Billing Address', 'dycore'); ?></h3>

		<div class="pure-g gutters">
			<div class="pure-u-1 pure-u-lg-1-3">

				<label for="address">
					<?php echo esc_html__('Address', 'dycore'); ?>
				</label>

				<input
					type="text"
					name="address"
					id="address"
					class="bottom-20"
					autocomplete="section-payment billing address-line1"
				>


			</div>

			<div class="pure-u-1 pure-u-lg-1-3">
				<label for="city">
					<?php echo esc_html__('City', 'dycore'); ?>
				</label>

				<input
					type="text"
					name="city"
					id="city"
					class="bottom-20"
					autocomplete="section-payment billing address-level2"
				>
			</div>

			<div class="pure-u-1 pure-u-lg-1-3">
				<label for="country">
					<?php echo esc_html__('Country', 'dycore'); ?>
				</label>

				<select
					name="country"
					id="country"
					class="countrylist bottom-20"
					autocomplete="section-payment billing country"
				>
					<option value="">--</option>
				</select>
			</div>
		</div>

		<hr>
	</div>

	<div class="dy_card_form_fields hidden">
		<h3><?php echo esc_html__('Card Details', 'dycore'); ?></h3>

		<?php echo apply_filters('dy_debug_instructions', null); ?>

		<p>
			<label for="CCNum">
				<?php echo esc_html__('Card Number', 'dycore'); ?>
			</label>

			<input
				type="text"
				inputmode="numeric"
				pattern="[0-9]{13,19}"
				maxlength="19"
				autocomplete="section-payment cc-number"
				class="large"
				name="CCNum"
				id="CCNum"
			>
		</p>

		<div class="pure-g gutters">
			<div class="pure-u-1 pure-u-lg-1-3">
				<label for="ExpMonth">
					<?php echo esc_html__('Expiration Month', 'dycore'); ?>
				</label>

				<select
					name="ExpMonth"
					id="ExpMonth"
					class="bottom-20"
					autocomplete="section-payment cc-exp-month"
				>
					<option value="">--</option>

					<?php for($month = 1; $month <= 12; $month++) : ?>
						<option value="<?php echo esc_attr($month); ?>">
							<?php echo esc_html(sprintf('%02d', $month)); ?>
						</option>
					<?php endfor; ?>
				</select>
			</div>

			<div class="pure-u-1 pure-u-lg-1-3">
				<label for="ExpYear">
					<?php echo esc_html__('Expiration Year', 'dycore'); ?>
				</label>

				<select
					name="ExpYear"
					id="ExpYear"
					class="bottom-20"
					autocomplete="section-payment cc-exp-year"
				>
					<option value="">--</option>

					<?php
					$current_year = (int) wp_date('Y');

					for($year = $current_year; $year < $current_year + 10; $year++) :
					?>
						<option value="<?php echo esc_attr($year); ?>">
							<?php echo esc_html($year); ?>
						</option>
					<?php endfor; ?>
				</select>
			</div>

			<div class="pure-u-1 pure-u-lg-1-3">
				<label for="CVV2">CVV</label>

				<input
					type="text"
					inputmode="numeric"
					pattern="[0-9]{3}"
					maxlength="3"
					autocomplete="section-payment cc-csc"
					name="CVV2"
					id="CVV2"
					class="bottom-20"
				>
			</div>
		</div>

		<hr>
	</div>
</div>