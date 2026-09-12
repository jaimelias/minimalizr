<?php

if ( !defined( 'WPINC' ) ) exit;

/**
 * Ultra-light input getters for WordPress with per-request caching.
 * Functions: secure_post, secure_get, secure_request, secure_cookie
 * Now: secure_get also safely falls back to get_query_var($key) when available.
 */

if ( ! function_exists( 'dy_secure_prepare_sanitizer' ) ) {

	/**
	 * Prepares a sanitizer callback, adding strict validation for numeric sanitizers.
	 *
	 * @param callable $sanitize_cb Valid sanitizer callback.
	 *
	 * @return callable The original callback or a validated numeric sanitizer wrapper.
	 */
	function dy_secure_prepare_sanitizer( callable $sanitize_cb ) : callable {

		$numeric_sanitizers = ['intval', 'absint', 'floatval'];

		if (
			is_string( $sanitize_cb )
			&& in_array( $sanitize_cb, $numeric_sanitizers, true )
		) {
			return static function ( mixed $value ) use ( $sanitize_cb ) : int|float|null {
				if ( is_bool( $value ) ) {
					return null;
				}

				return match ( $sanitize_cb ) {
					'intval' => (static function () use ( $value ) : int|null {
						$validated = filter_var( $value, FILTER_VALIDATE_INT );

						return false === $validated
							? null
							: (int) $validated;
					})(),

					'absint' => (static function () use ( $value ) : int|null {
						$validated = filter_var(
							$value,
							FILTER_VALIDATE_INT,
							[
								'options' => [
									'min_range' => 0,
								],
							]
						);

						return false === $validated
							? null
							: absint( $validated );
					})(),

					'floatval' => (static function () use ( $value ) : float|null {
						$validated = filter_var( $value, FILTER_VALIDATE_FLOAT );

						return false === $validated || ! is_finite( (float) $validated )
							? null
							: (float) $validated;
					})(),
				};
			};
		}

		return $sanitize_cb;
	}
}

if ( ! function_exists( '_secure_input' ) ) {

	/**
	 * Reads and sanitizes a scalar input value from an allowed request source.
	 *
	 * @param 'POST'|'GET'|'REQUEST'|'COOKIE' $source_name Request source.
	 * @param string                          $key         Input key.
	 * @param string|int|float|bool|null      $default     Value returned when input is missing or invalid.
	 * @param callable|string                 $sanitize_cb Sanitizer callback or the internal "exists" sentinel.
	 *
	 * @return string|int|float|bool|null Sanitized scalar value, existence flag, or default value.
	 */
	function _secure_input(
		string $source_name,
		string $key,
		string|int|float|bool|null $default = '',
		callable|string $sanitize_cb = 'sanitize_text_field'
	) : string|int|float|bool|null {

		if ( ! in_array( $source_name, ['POST', 'GET', 'REQUEST', 'COOKIE'], true ) ) {
			throw new ValueError(
				sprintf( 'Invalid secure input source: %s', $source_name )
			);
		}

		/**
		 * @var array{
		 *     POST: array<string, string|int|float|bool>,
		 *     GET: array<string, string|int|float|bool>,
		 *     REQUEST: array<string, string|int|float|bool>,
		 *     COOKIE: array<string, string|int|float|bool>,
		 *     QVAR: array<string, string|int|float|bool>
		 * } $cache
		 */
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
					return array_key_exists( $key, $_POST )
						&& is_scalar( $_POST[ $key ] );

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
					return array_key_exists( $key, $_REQUEST )
						&& is_scalar( $_REQUEST[ $key ] );

				case 'COOKIE':
					return array_key_exists( $key, $_COOKIE )
						&& is_scalar( $_COOKIE[ $key ] );
			}
		}

		// Resolve superglobal by name.
		switch ( $source_name ) {
			case 'POST':
				$src =& $_POST;
				break;

			case 'GET':
				$src =& $_GET;
				break;

			case 'REQUEST':
				$src =& $_REQUEST;
				break;

			case 'COOKIE':
				$src =& $_COOKIE;
				break;
		}

		/*
		 * The string type is required because PHP cannot express
		 * "callable string or the literal 'exists'" natively.
		 */
		if ( ! is_callable( $sanitize_cb ) ) {
			throw new TypeError(
				'$sanitize_cb must be callable or the string "exists".'
			);
		}

		$sanitizer = dy_secure_prepare_sanitizer( $sanitize_cb );
		$cacheable = is_string( $sanitize_cb );
		$cache_id  = $cacheable
			? $key . '|' . $sanitize_cb
			: '';

		// Fast path: superglobal hit.
		if ( array_key_exists( $key, $src ) ) {
			$value = $src[ $key ];

			if ( ! is_scalar( $value ) ) {
				return $default;
			}

			if (
				$cacheable
				&& array_key_exists( $cache_id, $cache[ $source_name ] )
			) {
				return $cache[ $source_name ][ $cache_id ];
			}

			$value     = wp_unslash( $value );
			$sanitized = $sanitizer( $value );

			if ( ! is_scalar( $sanitized ) ) {
				return $default;
			}

			if ( $cacheable ) {
				$cache[ $source_name ][ $cache_id ] = $sanitized;
			}

			return $sanitized;
		}

		// Fallback: safely read from get_query_var() when the query is ready.
		if ( $source_name === 'GET' ) {
			if (
				did_action( 'parse_request' )
				&& isset( $GLOBALS['wp'] )
				&& $GLOBALS['wp'] instanceof WP
				&& is_array( $GLOBALS['wp']->query_vars )
			) {
				if ( array_key_exists( $key, $GLOBALS['wp']->query_vars ) ) {
					$qv = $GLOBALS['wp']->query_vars[ $key ];

					if ( ! is_scalar( $qv ) ) {
						return $default;
					}

					if (
						$cacheable
						&& array_key_exists( $cache_id, $cache['QVAR'] )
					) {
						return $cache['QVAR'][ $cache_id ];
					}

					$qv        = wp_unslash( $qv );
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
				function_exists( 'get_query_var' )
				&& (
					did_action( 'parse_query' )
					|| did_action( 'wp' )
					|| (
						isset( $GLOBALS['wp_query'] )
						&& $GLOBALS['wp_query'] instanceof WP_Query
					)
				)
			) {
				$qv = get_query_var( $key, null );

				if ( $qv !== null ) {
					if ( ! is_scalar( $qv ) ) {
						return $default;
					}

					if (
						$cacheable
						&& array_key_exists( $cache_id, $cache['QVAR'] )
					) {
						return $cache['QVAR'][ $cache_id ];
					}

					$qv        = wp_unslash( $qv );
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

		return $default;
	}
}


if ( ! function_exists( 'secure_post' ) ) {

	/**
	 * Reads and sanitizes a scalar POST parameter.
	 *
	 * @param string                     $key         Input key.
	 * @param string|int|float|bool|null $default     Default value.
	 * @param callable|string            $sanitize_cb Sanitizer callback.
	 *
	 * @return string|int|float|bool|null Sanitized value or default.
	 */
	function secure_post(
		string $key,
		string|int|float|bool|null $default = '',
		callable|string $sanitize_cb = 'sanitize_text_field'
	) : string|int|float|bool|null {
		return _secure_input( 'POST', $key, $default, $sanitize_cb );
	}
}


if ( ! function_exists( 'secure_get' ) ) {

	/**
	 * Reads and sanitizes a scalar GET parameter with safe query-var fallback.
	 *
	 * Works reliably after parse_query or later.
	 *
	 * @param string                     $key         Input key.
	 * @param string|int|float|bool|null $default     Default value.
	 * @param callable|string            $sanitize_cb Sanitizer callback.
	 *
	 * @return string|int|float|bool|null Sanitized value or default.
	 */
	function secure_get(
		string $key,
		string|int|float|bool|null $default = '',
		callable|string $sanitize_cb = 'sanitize_text_field'
	) : string|int|float|bool|null {
		return _secure_input( 'GET', $key, $default, $sanitize_cb );
	}
}


if ( ! function_exists( 'secure_request' ) ) {

	/**
	 * Reads and sanitizes a scalar REQUEST parameter.
	 *
	 * @param string                     $key         Input key.
	 * @param string|int|float|bool|null $default     Default value.
	 * @param callable|string            $sanitize_cb Sanitizer callback.
	 *
	 * @return string|int|float|bool|null Sanitized value or default.
	 */
	function secure_request(
		string $key,
		string|int|float|bool|null $default = '',
		callable|string $sanitize_cb = 'sanitize_text_field'
	) : string|int|float|bool|null {
		return _secure_input( 'REQUEST', $key, $default, $sanitize_cb );
	}
}


if ( ! function_exists( 'secure_cookie' ) ) {

	/**
	 * Reads and sanitizes a scalar COOKIE parameter.
	 *
	 * @param string                     $key         Input key.
	 * @param string|int|float|bool|null $default     Default value.
	 * @param callable|string            $sanitize_cb Sanitizer callback.
	 *
	 * @return string|int|float|bool|null Sanitized value or default.
	 */
	function secure_cookie(
		string $key,
		string|int|float|bool|null $default = '',
		callable|string $sanitize_cb = 'sanitize_text_field'
	) : string|int|float|bool|null {
		return _secure_input( 'COOKIE', $key, $default, $sanitize_cb );
	}
}


if ( ! function_exists( 'get_has' ) ) {

	/**
	 * Determines whether a scalar GET parameter exists.
	 *
	 * @param string $key Input key.
	 *
	 * @return bool True when the parameter exists and is scalar.
	 */
	function get_has( string $key ) : bool {
		return _secure_input( 'GET', $key, '', 'exists' );
	}
}


if ( ! function_exists( 'post_has' ) ) {

	/**
	 * Determines whether a scalar POST parameter exists.
	 *
	 * @param string $key Input key.
	 *
	 * @return bool True when the parameter exists and is scalar.
	 */
	function post_has( string $key ) : bool {
		return _secure_input( 'POST', $key, '', 'exists' );
	}
}


if ( ! function_exists( 'request_has' ) ) {

	/**
	 * Determines whether a scalar REQUEST parameter exists.
	 *
	 * @param string $key Input key.
	 *
	 * @return bool True when the parameter exists and is scalar.
	 */
	function request_has( string $key ) : bool {
		return _secure_input( 'REQUEST', $key, '', 'exists' );
	}
}


if ( ! function_exists( 'cookie_has' ) ) {

	/**
	 * Determines whether a scalar COOKIE parameter exists.
	 *
	 * @param string $key Input key.
	 *
	 * @return bool True when the parameter exists and is scalar.
	 */
	function cookie_has( string $key ) : bool {
		return _secure_input( 'COOKIE', $key, '', 'exists' );
	}
}