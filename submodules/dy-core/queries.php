<?php
/**
 * Ultra-light input getters for WordPress with per-request caching.
 * Functions: secure_post, secure_get, secure_request, secure_cookie
 * Now: secure_get also safely falls back to get_query_var($key) when available.
 */

if ( ! function_exists( '_secure_apply_recursive' ) ) {
	function _secure_apply_recursive( $value, $sanitizer ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $k => $v ) {
				$value[ $k ] = _secure_apply_recursive( $v, $sanitizer );
			}
			return $value;
		}
		return $sanitizer( $value );
	}
}

if ( ! function_exists( '_secure_prepare_sanitizer' ) ) {
	function _secure_prepare_sanitizer( $sanitize_cb ) {
		if ( is_string( $sanitize_cb ) && function_exists( $sanitize_cb ) ) {
			return $sanitize_cb;
		}
		if ( is_callable( $sanitize_cb ) ) {
			return $sanitize_cb;
		}
		return static function( $v ) { return $v; }; // no-op fallback
	}
}

if ( ! function_exists( '_secure_input' ) ) {
	function _secure_input( $source_name, $key, $default = '', $sanitize_cb = 'sanitize_text_field' ) {
		static $cache = [
			'POST'    => [],
			'GET'     => [],
			'REQUEST' => [],
			'COOKIE'  => [],
			'QVAR'    => [],
		];

		// Handle "exists" special sanitizer first.
		if ( $sanitize_cb === 'exists' ) {
			switch ( $source_name ) {
				case 'POST':    return array_key_exists( $key, $_POST );
				case 'GET':     return array_key_exists( $key, $_GET )
					|| ( did_action( 'parse_request' ) && isset( $GLOBALS['wp'] ) && $GLOBALS['wp'] instanceof WP && array_key_exists( $key, $GLOBALS['wp']->query_vars ) )
					|| ( function_exists( 'get_query_var' ) && ( did_action( 'parse_query' ) || did_action( 'wp' ) ) && get_query_var( $key, null ) !== null );
				case 'REQUEST': return array_key_exists( $key, $_REQUEST );
				case 'COOKIE':  return array_key_exists( $key, $_COOKIE );
			}
			return false;
		}

		// Resolve superglobal by name.
		switch ( $source_name ) {
			case 'POST':    $src =& $_POST;    break;
			case 'GET':     $src =& $_GET;     break;
			case 'REQUEST': $src =& $_REQUEST; break;
			case 'COOKIE':  $src =& $_COOKIE;  break;
			default:        $src = [];         break;
		}

		$sanitizer = _secure_prepare_sanitizer( $sanitize_cb );
		$san_id    = is_string( $sanitize_cb ) ? $sanitize_cb : 'callable';
		$cache_id  = $key . '|' . $san_id;

		// Fast path: superglobal hit
		if ( array_key_exists( $key, $src ) ) {
			if ( array_key_exists( $cache_id, $cache[ $source_name ] ) ) {
				return $cache[ $source_name ][ $cache_id ];
			}

			$value = wp_unslash( $src[ $key ] );
			$sanitized = is_array( $value ) ? _secure_apply_recursive( $value, $sanitizer ) : $sanitizer( $value );
			$cache[ $source_name ][ $cache_id ] = $sanitized;
			return $sanitized;
		}

		// Fallback: safely read from get_query_var($key) if the query is ready (only for GET).
		if ( $source_name === 'GET' ) {
			if ( did_action( 'parse_request' ) && isset( $GLOBALS['wp'] ) && $GLOBALS['wp'] instanceof WP && is_array( $GLOBALS['wp']->query_vars ) ) {
				if ( array_key_exists( $key, $GLOBALS['wp']->query_vars ) ) {
					if ( array_key_exists( $cache_id, $cache['QVAR'] ) ) {
						return $cache['QVAR'][ $cache_id ];
					}
					$qv = $GLOBALS['wp']->query_vars[ $key ];
					$sanitized = is_array( $qv ) ? _secure_apply_recursive( $qv, $sanitizer ) : $sanitizer( $qv );
					$cache['QVAR'][ $cache_id ] = $sanitized;
					return $sanitized;
				}
			}

			if (
				function_exists( 'get_query_var' ) &&
				( did_action( 'parse_query' ) || did_action( 'wp' ) || ( isset( $GLOBALS['wp_query'] ) && $GLOBALS['wp_query'] instanceof WP_Query ) )
			) {
				if ( array_key_exists( $cache_id, $cache['QVAR'] ) ) {
					return $cache['QVAR'][ $cache_id ];
				}
				$qv = get_query_var( $key, null );
				if ( $qv !== null ) {
					$sanitized = is_array( $qv ) ? _secure_apply_recursive( $qv, $sanitizer ) : $sanitizer( $qv );
					$cache['QVAR'][ $cache_id ] = $sanitized;
					return $sanitized;
				}
			}
		}

		// Miss -> return default.
		return $default;
	}
}


if ( ! function_exists( 'secure_post' ) ) {
	function secure_post( $key, $default = '', $sanitize_cb = 'sanitize_text_field' ) {
		return _secure_input( 'POST', $key, $default, $sanitize_cb );
	}
}
if ( ! function_exists( 'secure_get' ) ) {
	/**
	 * GET with safe get_query_var fallback.
	 * Works reliably after 'parse_query' (or later).
	 */
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

if ( ! function_exists( 'get_has' ) ) {
	function get_has( $key ) {
		return _secure_input( 'GET', $key, '', 'exists' );
	}
}
if ( ! function_exists( 'post_has' ) ) {
	function post_has( $key ) {
		return _secure_input( 'POST', $key, '', 'exists' );
	}
}
if ( ! function_exists( 'request_has' ) ) {
	function request_has( $key ) {
		return _secure_input( 'REQUEST', $key, '', 'exists' );
	}
}
if ( ! function_exists( 'cookie_has' ) ) {
	function cookie_has( $key ) {
		return _secure_input( 'COOKIE', $key, '', 'exists' );
	}
}