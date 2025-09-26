<?php
// Internal helper to DRY up sanitization.
if ( ! function_exists( '_secure_input' ) ) {
	/**
	 * Internal secure input getter.
	 *
	 * @param array           $source      Superglobal array ($_POST, $_GET, etc).
	 * @param string          $key         Array key to retrieve.
	 * @param mixed           $default     Default value if key not set.
	 * @param callable|string $sanitize_cb Sanitizer function (default: sanitize_text_field).
	 * @return mixed
	 */
	function _secure_input( $source, $key, $default = '', $sanitize_cb = 'sanitize_text_field' ) {
		if ( ! is_array( $source ) || ! array_key_exists( $key, $source ) ) {
			return $default;
		}

		$value = wp_unslash( $source[ $key ] ); // WP auto-adds slashes to input.

		if ( is_array( $value ) ) {
			// Sanitize recursively (WordPress provides map_deep).
			return map_deep( $value, $sanitize_cb );
		}

		return is_callable( $sanitize_cb ) ? call_user_func( $sanitize_cb, $value ) : $value;
	}
}

if ( ! function_exists( 'secure_post' ) ) {
	/**
	 * Get a sanitized value from $_POST.
	 */
	function secure_post( $key, $default = '', $sanitize_cb = 'sanitize_text_field' ) {
		return _secure_input( $_POST, $key, $default, $sanitize_cb );
	}
}

if ( ! function_exists( 'secure_get' ) ) {
	/**
	 * Get a sanitized value from $_GET.
	 */
	function secure_get( $key, $default = '', $sanitize_cb = 'sanitize_text_field' ) {
		return _secure_input( $_GET, $key, $default, $sanitize_cb );
	}
}

if ( ! function_exists( 'secure_request' ) ) {
	/**
	 * Get a sanitized value from $_REQUEST (GET + POST).
	 * Prefer secure_post()/secure_get() when possible.
	 */
	function secure_request( $key, $default = '', $sanitize_cb = 'sanitize_text_field' ) {
		return _secure_input( $_REQUEST, $key, $default, $sanitize_cb );
	}
}

if ( ! function_exists( 'secure_cookie' ) ) {
	/**
	 * Get a sanitized value from $_COOKIE.
	 */
	function secure_cookie( $key, $default = '', $sanitize_cb = 'sanitize_text_field' ) {
		return _secure_input( $_COOKIE, $key, $default, $sanitize_cb );
	}
}


?>