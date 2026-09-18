<?php



if(!function_exists('secure_server')) {

    function secure_server( string $key ) : string {

        if($key === '') {
            return '';
        }

        static $cache = [];

        if(array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        $value = $_SERVER[ $key ] ?? '';

        $value = is_scalar( $value )
            ? trim( (string) wp_unslash( $value ) )
            : '';

        if($key === 'REQUEST_METHOD') {
            $value = strtoupper( $value );
        }

        return $cache[$key] = $value;
    }
}

if ( ! function_exists( 'get_request_country_code' ) ) {
	/**
	 * Get the visitor country code provided by Cloudflare.
	 *
	 * @return string ISO 3166-1 alpha-2 country code, "XX", "T1", or an empty string.
	 */
	function get_request_country_code(): string {

		$value = secure_server('HTTP_CF_IPCOUNTRY') ?? '';

		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$country = strtoupper( trim( (string) $value ) );

		return preg_match( '/^[A-Z0-9]{2}$/', $country )
			? $country
			: '';
	}
}

if(!function_exists('get_ip_address'))
{
	/**
	 * Return the normalized client IP, or an empty string if it cannot be trusted.
	 */
	function get_ip_address(): string
	{
		$ip = secure_server('REMOTE_ADDR');

		if(!filter_var($ip, FILTER_VALIDATE_IP)) {
			return '';
		}

		// Match the proxy policy used by cloudflare_ban_ip_address().

		

		if (
			dy_ip_in_cidr_range(
				$ip,
				get_cloudflare_proxy_ranges(),
				'cloudflare_proxy_ranges'
			)
		) {
			$ip = secure_server('HTTP_CF_CONNECTING_IP');

			if(!filter_var($ip, FILTER_VALIDATE_IP)) {
				return '';
			}
		}

		$packed_ip = inet_pton($ip);

		return $packed_ip !== false
			? inet_ntop($packed_ip)
			: '';
	}
	
}

?>
