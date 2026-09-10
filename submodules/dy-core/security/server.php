<?php



if(!function_exists('secure_server')) {

    function secure_server( string $key )  : string {

        if($key === '') {
            return '';
        }

        static $cache = [];

        if(array_key_exists($key, $cache)) {
            return $cache[$key];
        }


        $value = $_SERVER[ $key ] ?? '';

        return $cache[$key] = is_scalar( $value )
            ? trim( (string) wp_unslash( $value ) )
            : '';
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


if(!function_exists('current_url_full')) {
	function current_url_full(): string
	{
		// Detect scheme (https/http), considering reverse proxies.
		$isHttps = (
			(!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
			|| (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
			|| (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
		);
		$scheme = $isHttps ? 'https' : 'http';

		// Determine host (prefer proxy header if present; take the first value).
		$host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
		if (!empty($_SERVER['HTTP_X_FORWARDED_HOST'])) {
			$xfh  = explode(',', $_SERVER['HTTP_X_FORWARDED_HOST']);
			$host = trim($xfh[0]); // Use the left-most host
		}

		// Extract hostname and port if HTTP_HOST already includes a port.
		$hostname = $host;
		$hostPort = null;
		if (strpos($host, ':') !== false) {
			[$hostname, $maybePort] = explode(':', $host, 2);
			if (ctype_digit($maybePort)) {
				$hostPort = (int)$maybePort;
			}
		}

		// Prefer forwarded port if supplied.
		if (!empty($_SERVER['HTTP_X_FORWARDED_PORT']) && ctype_digit($_SERVER['HTTP_X_FORWARDED_PORT'])) {
			$hostPort = (int)$_SERVER['HTTP_X_FORWARDED_PORT'];
		} elseif (empty($hostPort) && !empty($_SERVER['SERVER_PORT']) && ctype_digit((string)$_SERVER['SERVER_PORT'])) {
			$hostPort = (int)$_SERVER['SERVER_PORT'];
		}

		// Omit default ports.
		$defaultPort = $isHttps ? 443 : 80;
		$portPart    = ($hostPort && $hostPort !== $defaultPort) ? ':' . $hostPort : '';

		// Request URI (path + query + fragment if present).
		$requestUri = $_SERVER['REQUEST_URI'] ?? '/';

		$url = $scheme . '://' . $hostname . $portPart . $requestUri;

		// If WordPress is loaded, return an escaped version.
		if (function_exists('esc_url_raw')) {
			return esc_url_raw($url);
		}

		// Plain PHP: lightly validate/sanitize.
		return filter_var($url, FILTER_SANITIZE_URL) ?: $url;
	}
}

?>
