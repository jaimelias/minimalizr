<?php



if(!function_exists('get_cloudflare_proxy_ranges')) {
	/**
	 * @return array<array-key, mixed>
	 */
    function get_cloudflare_proxy_ranges() : array {
        return apply_filters('dy_cloudflare_proxy_ranges', [
			'173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22',
			'141.101.64.0/18', '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20',
			'197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
			'104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22', '2400:cb00::/32',
			'2606:4700::/32', '2803:f800::/32', '2405:b500::/32', '2405:8100::/32',
			'2a06:98c0::/29', '2c0f:f248::/32'
		]);
    }
}


if(!function_exists('is_cloudflare_proxied')) {

	function is_cloudflare_proxied(): bool {
		//do not use get_ip_address here
		$remote_ip = secure_server('REMOTE_ADDR');

		return filter_var($remote_ip, FILTER_VALIDATE_IP) !== false
			&& dy_ip_in_cidr_range(
				$remote_ip,
				get_cloudflare_proxy_ranges(),
				'cloudflare_proxy_ranges'
			);
	}

}


if ( ! function_exists( 'dy_ip_in_cidr' ) ) {

	function dy_ip_in_cidr(
		string $ip,
		mixed $cidr,
		string $range_owner = ''
	): bool {

		if ( $ip === '' || ! is_string( $cidr ) ) {
			return false;
		}

		static $cache = [];

		$cache_key = hash(
			'md5',
			strlen( $range_owner ) . ':' . $range_owner
			. strlen( $ip ) . ':' . $ip
			. strlen( $cidr ) . ':' . $cidr
		);

		if ( array_key_exists( $cache_key, $cache ) ) {
			return $cache[ $cache_key ];
		}

		$parts = explode( '/', $cidr, 2 );

		if ( count( $parts ) !== 2 || ! ctype_digit( $parts[1] ) ) {
			return $cache[ $cache_key ] = false;
		}

		$ip_binary      = inet_pton( $ip );
		$network_binary = inet_pton( $parts[0] );

		if (
			$ip_binary === false
			|| $network_binary === false
			|| strlen( $ip_binary ) !== strlen( $network_binary )
		) {
			return $cache[ $cache_key ] = false;
		}

		$prefix_length = (int) $parts[1];
		$address_bits  = strlen( $ip_binary ) * 8;

		if ( $prefix_length > $address_bits ) {
			return $cache[ $cache_key ] = false;
		}

		$whole_bytes    = intdiv( $prefix_length, 8 );
		$remaining_bits = $prefix_length % 8;

		if (
			$whole_bytes > 0
			&& substr( $ip_binary, 0, $whole_bytes )
				!== substr( $network_binary, 0, $whole_bytes )
		) {
			return $cache[ $cache_key ] = false;
		}

		if ( $remaining_bits === 0 ) {
			return $cache[ $cache_key ] = true;
		}

		$mask = ( 0xff << ( 8 - $remaining_bits ) ) & 0xff;

		return $cache[ $cache_key ] = (
			( ord( $ip_binary[ $whole_bytes ] ) & $mask )
			=== ( ord( $network_binary[ $whole_bytes ] ) & $mask )
		);
	}
}


if ( ! function_exists( 'dy_ip_in_cidr_range' ) ) {

	/**
	 * @param array<array-key, mixed> $ranges
	 */
	function dy_ip_in_cidr_range( string $ip, array $ranges, string $range_owner ): bool {

		static $cache = [];

		$string_ranges = [];
		$range_signature = '';

		foreach ( $ranges as $range ) {
			if ( is_string( $range ) ) {
				$string_ranges[] = $range;
				$range_signature .= strlen( $range ) . ':' . $range;
			}
		}

		$cache_key = hash(
			'md5',
			strlen( $range_owner ) . ':' . $range_owner
			. strlen( $ip ) . ':' . $ip
			. $range_signature
		);

		if ( array_key_exists( $cache_key, $cache ) ) {
			return $cache[ $cache_key ];
		}

		foreach ( $string_ranges as $range ) {

			if ( dy_ip_in_cidr( $ip, $range, $range_owner ) ) {
				return $cache[ $cache_key ] = true;
			}
		}

		return $cache[ $cache_key ] = false;
	}
}
