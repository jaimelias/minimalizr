<?php

/**
 * Generated by the WordPress Meta Box generator
 * at http://jeremyhixon.com/tool/wordpress-meta-box-generator/
 */

function minimalizr_get_meta( $value ) {
	global $post;
	if($post)
	{
		$field = get_post_meta( $post->ID, $value, true );
		if ( ! empty( $field ) ) {
			return is_array( $field ) ? stripslashes_deep( $field ) : stripslashes( wp_kses_decode_entities( $field ) );
		} else {
			return false;
		}		
	}
}

function minimalizr_add_meta_box() {
	add_meta_box(
		'minimalizr-page-layout',
		__( 'Minimalizr - Page Layout', 'minimalizr' ),
		'minimalizr_width_html',
		array('page', 'post'),
		'side',
		'default'
	);
}
add_action( 'add_meta_boxes', 'minimalizr_add_meta_box' );

function minimalizr_width_html( $post) {
	wp_nonce_field( '_minimalizr_nonce', 'minimalizr_nonce' ); ?>

	<p>
		<label for="minimalizr_width"><?php _e( 'Width', 'minimalizr' ); ?></label><br>
		<select name="minimalizr_width" id="minimalizr_width">
			<option value="fixed" <?php echo (minimalizr_get_meta( 'minimalizr_width' ) === 'fixed' ) ? 'selected' : '' ?>><?php esc_html(_e("Fixed", "minimalizr")); ?></option>		
			<option value="full" <?php echo (minimalizr_get_meta( 'minimalizr_width' ) === 'full' ) ? 'selected' : '' ?>><?php esc_html(_e("Full", "minimalizr")); ?></option>
		</select>
	</p><?php
}

function minimalizr_save( $post_id ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
	if ( ! isset( $_POST['minimalizr_nonce'] ) || ! wp_verify_nonce( $_POST['minimalizr_nonce'], '_minimalizr_nonce' ) ) return;
	if ( ! current_user_can( 'edit_post', $post_id ) ) return;
	//width
	if ( isset( $_POST['minimalizr_width'] ) )
		update_post_meta( $post_id, 'minimalizr_width', esc_attr( $_POST['minimalizr_width'] ) );
}
add_action( 'save_post', 'minimalizr_save' );

/*
	Usage: minimalizr_get_meta( 'minimalizr_width' )
*/




?>