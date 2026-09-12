<?php

if ( ! function_exists( 'dy_get_option' ) ) {

	/**
	 * Gets, optionally sanitizes, and caches a WordPress option.
	 *
	 * @param string          $cache_key   Option key.
	 * @param string          $def         Default option value.
	 * @param callable|string $sanitize_cb Sanitizer callback. Empty string disables sanitization.
	 *
	 * @return string|int|float|bool|null Option value or sanitized scalar value.
	 */
	function dy_get_option(
		string $cache_key = '',
		string $def = '',
		callable|string $sanitize_cb = ''
	) : string|int|float|bool|null {

		if ( $cache_key === '' ) {
			return '';
		}

		static $cache = [];

		$cache_id = is_string( $sanitize_cb )
			? $cache_key . '|' . $sanitize_cb
			: null;

		if (
			$cache_id !== null
			&& array_key_exists( $cache_id, $cache )
		) {
			return $cache[ $cache_id ];
		}

		$value = trim( (string) get_option( $cache_key, $def ) );

		if ( $sanitize_cb === '' ) {
			$result = $value;
		} else {
			$sanitizer = _secure_prepare_sanitizer( $sanitize_cb );
			$result    = $sanitizer( $value );

			if ( ! is_scalar( $result ) && $result !== null ) {

				$encoded = wp_json_encode($result);

				write_log(
					"dy_get_option can only be used with scalar values: $encoded",
					true,
					false,
					'unexpected_value'
				);
				$result = null;
			}
		}

		if ( $cache_id !== null ) {
			$cache[ $cache_id ] = $result;
		}

		return $result;
	}
}


?>