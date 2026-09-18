<?php

if ( !defined( 'WPINC' ) ) exit;

#[AllowDynamicProperties]
class Dy_Booking_Page {

	static $cache = [];

    public function __construct($version) {

        add_action('dy_terms_conditions', [$this, 'terms_conditions']);
    }

	public function terms_conditions(): void
	{
		$terms_conditions = (array) dy_get_taxonomies('package_terms_conditions');
		
		if(empty($terms_conditions)) {
			return;
		}
		
		$output = '<h3>'.esc_html(__('Terms & Conditions', 'dynamicpackages')).'</h3><p>';
		
		for($x = 0; $x < count($terms_conditions); $x++ )
		{
			$term = $terms_conditions[$x];
			$id = $term->term_taxonomy_id;
			$url = get_term_link($id);

			if (is_wp_error($url)) {
				continue;
			}

			$name = $term->name;

			
			$output .= sprintf(
				'<label for="terms_conditions_%1$s" class="checkmark-container"><input type="checkbox" name="terms_conditions_%1$s" id="terms_conditions_%1$s" class="required" /><span class="checkmark"></span> <a href="%2$s" target="_blank">%3$s</a></label>',
				esc_attr( $id ),
				esc_url( $url ),
				esc_html( $name )
			);

		}

		$output .= '</p><hr/>';

		echo $output;
		
	}

}