<?php

if ( !defined( 'WPINC' ) ) exit;

if(!function_exists('handsontable')) {
	function handsontable($args)
	{
		$output = '';
		
		if(!is_array($args)) {
			write_log('Invalid $args param in "handsontable".');
			return '';
		}

		if(!is_string($args['container']) || trim($args['container']) === '') {
			write_log('Invalid $args["container"] property in "handsontable".');
			return '';
		}

		if(!array_key_exists('textarea', $args) || !is_string($args['textarea']) || trim($args['textarea']) === '') {
			write_log('Invalid $args["textarea"] property in "handsontable".');
			return '';
		}

		if(!array_key_exists('headers', $args) || !is_array($args['headers']) || count($args['headers']) === 0) {
			write_log('Invalid $args["headers"] property in "handsontable".');
			return '';
		}

		if(!array_key_exists('type', $args) || !is_array($args['type']) || count($args['type']) === 0) {
			write_log('Invalid $args["type"] property in "handsontable".');
			return '';
		}

		//max actually refers to the input or select field name that controls the max number of rows to render. It is not a numeric value.
		if(!array_key_exists('max', $args) || !is_string($args['max']) || trim($args['max']) === '') {
			write_log('Invalid $args["max"] property in "handsontable".');
			return '';
		}
		
		if(!array_key_exists('value', $args) || !is_string($args['value'])) {
			write_log('Invalid $args["value"] property in "handsontable".');
			return '';
		}

		$default_arr = array_fill( 0, count( $args['headers'] ), null );
		$default_val = wp_json_encode([ 
				$args['container'] => $default_arr
		]);
		
		$content_is_valid = json_decode(html_entity_decode($args['value']), true);
		$content_is_valid = is_array($content_is_valid) && array_key_exists($args['container'], $content_is_valid);

		$args['value'] = ($content_is_valid) ? $args['value'] : $default_val;

		$dropdown = null;

		if(array_key_exists('dropdown', $args)) {

			if(!is_array($args['dropdown']) || count($args['dropdown']) === 0) {
				write_log('Invalid $args["dropdown"] property in "handsontable".');
				return '';
			}

			$dropdown_list = implode(',', $args['dropdown']);

			$dropdown = 'data-sensei-dropdown="'.esc_attr($dropdown_list).'"';
		}
		
		$disabled = (array_key_exists('disabled', $args)) ? $args['disabled'] : null;

		
		$output = sprintf(
			'<div class="hot-container">
				<div 
					id="%1$s" 
					class="hot" 
					data-sensei-max="%2$s" 
					data-sensei-container="%1$s" 
					data-sensei-textarea="%3$s" 
					data-sensei-headers="%4$s" 
					data-sensei-type="%5$s" 
					%6$s 
					data-sensei-disabled="%7$s"></div>
			</div>
			<div class="hidden">
				<textarea cols="100" rows="20" name="%3$s" id="%3$s">%8$s</textarea>
			</div>',
			esc_attr( $args['container'] ),
			esc_attr( $args['max'] ),
			esc_attr( $args['textarea'] ),
			esc_attr( implode( ',', $args['headers'] ) ),
			esc_attr( implode( ',', $args['type'] ) ),
			$dropdown,
			esc_attr( $disabled ),
			esc_textarea( $args['value'] )
		);
		
		return $output;
	}
}


?>