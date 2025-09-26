<?php
/**
 * Ultra-light input getters for WordPress with per-request caching.
 * Functions: secure_post, secure_get, secure_request, secure_cookie
 */

if ( ! function_exists( '_secure_apply_recursive' ) ) {
	/**
	 * Apply a sanitizer to a scalar or recursively to an array.
	 *
	 * @param mixed    $value
	 * @param callable $sanitizer  e.g. 'sanitize_text_field'
	 * @return mixed
	 */
	function _secure_apply_recursive( $value, $sanitizer ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $k => $v ) {
				$value[ $k ] = _secure_apply_recursive( $v, $sanitizer );
			}
			return $value;
		}
		// common fast-path: empty or scalar
		return $sanitizer( $value );
	}
}

if ( ! function_exists( '_secure_input' ) ) {
	/**
	 * Internal cached input getter.
	 *
	 * @param 'POST'|'GET'|'REQUEST'|'COOKIE' $source_name
	 * @param string                          $key
	 * @param mixed                           $default
	 * @param callable|string                 $sanitize_cb
	 * @return mixed
	 */
	function _secure_input( $source_name, $key, $default = '', $sanitize_cb = 'sanitize_text_field' ) {
		static $cache = [
			'POST'    => [],
			'GET'     => [],
			'REQUEST' => [],
			'COOKIE'  => [],
		];

		// Resolve superglobal by name.
		switch ( $source_name ) {
			case 'POST':    $src =& $_POST;    break;
			case 'GET':     $src =& $_GET;     break;
			case 'REQUEST': $src =& $_REQUEST; break;
			case 'COOKIE':  $src =& $_COOKIE;  break;
			default:        $src = [];         break;
		}

		// Respect explicit defaults; don't cache misses (so different defaults still work).
		if ( ! array_key_exists( $key, $src ) ) {
			return $default;
		}

		// Prepare sanitizer as a direct callable (faster than call_user_func).
		if ( is_string( $sanitize_cb ) && function_exists( $sanitize_cb ) ) {
			$sanitizer = $sanitize_cb;
		} elseif ( is_callable( $sanitize_cb ) ) {
			$sanitizer = $sanitize_cb; // already a callable
		} else {
			// No-op sanitizer fallback (unlikely, but safe).
			$sanitizer = static function( $v ) { return $v; };
		}

		// Cache key: key + sanitizer identity
		$san_id   = is_string( $sanitize_cb ) ? $sanitize_cb : 'callable';
		$cache_id = $key . '|' . $san_id;

		if ( array_key_exists( $cache_id, $cache[ $source_name ] ) ) {
			return $cache[ $source_name ][ $cache_id ];
		}

		// Unsplash once per key; sanitize once per (key, sanitizer).
		$value = wp_unslash( $src[ $key ] );

		// Recursively sanitize arrays; fast path for scalars.
		if ( is_array( $value ) ) {
			$sanitized = _secure_apply_recursive( $value, $sanitizer );
		} else {
			$sanitized = $sanitizer( $value );
		}

		$cache[ $source_name ][ $cache_id ] = $sanitized;
		return $sanitized;
	}
}

if ( ! function_exists( 'secure_post' ) ) {
	function secure_post( $key, $default = '', $sanitize_cb = 'sanitize_text_field' ) {
		return _secure_input( 'POST', $key, $default, $sanitize_cb );
	}
}
if ( ! function_exists( 'secure_get' ) ) {
	function secure_get( $key, $default = '', $sanitize_cb = 'sanitize_text_field' ) {
		return _secure_input( 'GET', $key, $default, $sanitize_cb );
	}
}
if ( ! function_exists( 'secure_request' ) ) {
	function secure_request( $key, $default = '', $sanitize_cb = 'sanitize_text_field' ) {
		return _secure_input( 'REQUEST', $key, $default, $sanitize_cb );
	}
}
if ( ! function_exists( 'secure_cookie' ) ) {
	function secure_cookie( $key, $default = '', $sanitize_cb = 'sanitize_text_field' ) {
		return _secure_input( 'COOKIE', $key, $default, $sanitize_cb );
	}
}
