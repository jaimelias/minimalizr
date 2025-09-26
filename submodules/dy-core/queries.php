<?php
// Internal helper to DRY things up.
if ( ! function_exists( '_secure_input' ) ) {
	function _secure_input( $source, $key, $default = '', $sanitize_cb = 'sanitize_text_field' ) {
		if ( ! is_array( $source ) || ! array_key_exists( $key, $source ) ) {
			return $default;
		}

		$value = wp_unslash( $source[ $key ] ); // WP adds slashes to input; remove first.

		if ( is_array( $value ) ) {
			// Recursively sanitize nested arrays.
			return map_deep( $value, $sanitize_cb );
		}

		return is_callable( $sanitize_cb ) ? call_user_func( $sanitize_cb, $value ) : $value;
	}
}

if ( ! function_exists( 'secure_get' ) ) {
	/**
	 * Get a sanitized value from $_GET.
	 *
	 * @param string          $key
	 * @param mixed           $default
	 * @param callable|string $sanitize_cb  e.g. 'sanitize_text_field', 'esc_url_raw'
	 * @return mixed
	 */
	function secure_get( $key, $default = '', $sanitize_cb = 'sanitize_text_field' ) {
		return _secure_input( $_GET, $key, $default, $sanitize_cb );
	}
}

if ( ! function_exists( 'secure_request' ) ) {
	/**
	 * Get a sanitized value from $_REQUEST (GET + POST).
	 * Prefer secure_get()/secure_post() when you can, to avoid ambiguity.
	 *
	 * @param string          $key
	 * @param mixed           $default
	 * @param callable|string $sanitize_cb
	 * @return mixed
	 */
	function secure_request( $key, $default = '', $sanitize_cb = 'sanitize_text_field' ) {
		return _secure_input( $_REQUEST, $key, $default, $sanitize_cb );
	}
}

if ( ! function_exists( 'secure_cookie' ) ) {
	/**
	 * Get a sanitized value from $_COOKIE.
	 *
	 * @param string          $key
	 * @param mixed           $default
	 * @param callable|string $sanitize_cb
	 * @return mixed
	 */
	function secure_cookie( $key, $default = '', $sanitize_cb = 'sanitize_text_field' ) {
		return _secure_input( $_COOKIE, $key, $default, $sanitize_cb );
	}
}


?>