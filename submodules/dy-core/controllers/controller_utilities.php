<?php

if ( ! defined( 'WPINC' ) ) exit;

if ( ! function_exists( 'dy_sanitize_per_line' ) ) {

	/**
	 * Sanitize a newline-delimited list of items using a whitelisted sanitizer.
	 *
	 * @param string $sanitizer_key Key identifying an allowed sanitize callback (see $allowed).
	 * @param string $str           Raw newline-delimited input.
	 * @param int    $max_items     Max number of items to keep.
	 * @return string
	 */
	function dy_sanitize_per_line( $sanitizer_key, $str, $max_items = 10 ) {

		// Whitelist of allowed sanitizers -- never call an arbitrary function name.
		$allowed = array(
			'sanitize_email' => 'sanitize_email',
			'sanitize_text_field' => 'sanitize_text_field',
		);

		if ( ! isset( $allowed[ $sanitizer_key ] ) || ! is_callable( $allowed[ $sanitizer_key ] ) ) {
			if ( function_exists( 'error_log' ) ) {
				error_log( sprintf( 'dy_sanitize_per_line: sanitizer "%s" is not allowed or not callable.', (string) $sanitizer_key ) );
			}
			return '';
		}

		if ( ! is_string( $str ) || '' === $str ) {
			return '';
		}

		// Cap input size to avoid abuse via huge payloads.
		$max_len = 20000;
		if ( function_exists( 'mb_strlen' ) ) {
			if ( mb_strlen( $str ) > $max_len ) {
				$str = mb_substr( $str, 0, $max_len );
			}
		} elseif ( strlen( $str ) > $max_len ) {
			$str = substr( $str, 0, $max_len );
		}

		// Sanitize max_items to a sane bound.
		$max_items = (int) $max_items;
		if ( $max_items <= 0 ) {
			$max_items = 10;
		}
		$max_items = min( $max_items, 100 );

		$decoded = html_entity_decode( $str, ENT_QUOTES, 'UTF-8' );

		// Normalize all line-ending styles (\r\n, \r, \n) before splitting.
		$lines = preg_split( '/\r\n|\r|\n/', $decoded );
		if ( false === $lines ) {
			return '';
		}

		$lines = array_map( 'trim', $lines );

		$sanitized = array_map( $allowed[ $sanitizer_key ], $lines );

		// Explicit filter: drop only empty strings, not other falsy-but-valid values.
		$filtered = array_filter( $sanitized, static function ( $v ) {
			return is_string( $v ) && '' !== $v;
		} );

		$unique  = array_values( array_unique( $filtered ) );
		$limited = array_slice( $unique, 0, $max_items );

		return implode( "\n", $limited );
	}
}

if ( ! function_exists( 'dy_sanitize_email_per_line' ) ) {

	function dy_sanitize_email_per_line( $str, $max_items = 10 ) {
		return dy_sanitize_per_line( 'sanitize_email', $str, $max_items );
	}
}

?>