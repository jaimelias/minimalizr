<?php

if ( !defined( 'WPINC' ) ) exit;

/**
 * Ultra-light input getters for WordPress with per-request caching.
 * Functions: secure_post, secure_get, secure_request, secure_cookie
 * Now: secure_get also safely falls back to get_query_var($key) when available.
 */

if ( ! function_exists( '_secure_prepare_sanitizer' ) ) {
	function _secure_prepare_sanitizer( $sanitize_cb ) {
		if ( is_string( $sanitize_cb ) && function_exists( $sanitize_cb ) ) {
			return $sanitize_cb;
		}
		if ( is_callable( $sanitize_cb ) ) {
			return $sanitize_cb;
		}

		return 'sanitize_text_field';
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

		// A parameter exists only when its submitted value is scalar.
		if ( $sanitize_cb === 'exists' ) {
			switch ( $source_name ) {
				case 'POST':
					return array_key_exists( $key, $_POST ) && is_scalar( $_POST[ $key ] );

				case 'GET':
					if ( array_key_exists( $key, $_GET ) ) {
						return is_scalar( $_GET[ $key ] );
					}

					if (
						did_action( 'parse_request' )
						&& isset( $GLOBALS['wp'] )
						&& $GLOBALS['wp'] instanceof WP
						&& is_array( $GLOBALS['wp']->query_vars )
						&& array_key_exists( $key, $GLOBALS['wp']->query_vars )
					) {
						return is_scalar( $GLOBALS['wp']->query_vars[ $key ] );
					}

					if (
						function_exists( 'get_query_var' )
						&& ( did_action( 'parse_query' ) || did_action( 'wp' ) )
					) {
						$qv = get_query_var( $key, null );
						return $qv !== null && is_scalar( $qv );
					}

					return false;

				case 'REQUEST':
					return array_key_exists( $key, $_REQUEST ) && is_scalar( $_REQUEST[ $key ] );

				case 'COOKIE':
					return array_key_exists( $key, $_COOKIE ) && is_scalar( $_COOKIE[ $key ] );
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
		$cacheable = is_string( $sanitize_cb );
		$cache_id  = $cacheable ? $key . '|' . $sanitize_cb : null;

		// Fast path: superglobal hit
		if ( array_key_exists( $key, $src ) ) {
			$value = $src[ $key ];

			if ( ! is_scalar( $value ) ) {
				return $default;
			}

			if ( $cacheable && array_key_exists( $cache_id, $cache[ $source_name ] ) ) {
				return $cache[ $source_name ][ $cache_id ];
			}

			$value = wp_unslash( $value );
			$sanitized = $sanitizer( $value );

			if ( ! is_scalar( $sanitized ) ) {
				return $default;
			}
			
			if ( $cacheable ) {
				$cache[ $source_name ][ $cache_id ] = $sanitized;
			}

			return $sanitized;
		}

		// Fallback: safely read from get_query_var($key) if the query is ready (only for GET).
		if ( $source_name === 'GET' ) {
			if ( did_action( 'parse_request' ) && isset( $GLOBALS['wp'] ) && $GLOBALS['wp'] instanceof WP && is_array( $GLOBALS['wp']->query_vars ) ) {
				if ( array_key_exists( $key, $GLOBALS['wp']->query_vars ) ) {
					$qv = $GLOBALS['wp']->query_vars[ $key ];

					if ( ! is_scalar( $qv ) ) {
						return $default;
					}

					if ( $cacheable && array_key_exists( $cache_id, $cache['QVAR'] ) ) {
						return $cache['QVAR'][ $cache_id ];
					}

					$qv = wp_unslash( $qv );
					$sanitized = $sanitizer( $qv );

					if ( ! is_scalar( $sanitized ) ) {
						return $default;
					}

					if ( $cacheable ) {
						$cache['QVAR'][ $cache_id ] = $sanitized;
					}
					
					return $sanitized;
				}
			}

			if (
				function_exists( 'get_query_var' ) &&
				( did_action( 'parse_query' ) || did_action( 'wp' ) || ( isset( $GLOBALS['wp_query'] ) && $GLOBALS['wp_query'] instanceof WP_Query ) )
			) {
				$qv = get_query_var( $key, null );
				if ( $qv !== null ) {
					if ( ! is_scalar( $qv ) ) {
						return $default;
					}

					if ( $cacheable && array_key_exists( $cache_id, $cache['QVAR'] ) ) {
						return $cache['QVAR'][ $cache_id ];
					}

					$qv = wp_unslash( $qv );
					$sanitized = $sanitizer( $qv );

					if ( ! is_scalar( $sanitized ) ) {
						return $default;
					}

					if ( $cacheable ) {
						$cache['QVAR'][ $cache_id ] = $sanitized;
					}
					
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
