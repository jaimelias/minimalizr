<?php

if ( !defined( 'WPINC' ) ) exit;

define('DY_CORE_FUNCTIONS', true);


if(!function_exists('get_dy_id'))
{
	function get_dy_id(){
		global $post;

		$dy_id      = secure_request('dy_id', null, 'absint');
		$post_req   = secure_request('post_id', null, 'absint');
		$admin_post = secure_request('post', null, 'absint');

		$req_id = !empty($dy_id)
			? $dy_id
			: (!empty($post_req) ? $post_req : null);

		$post_id = $post instanceof WP_Post
			? $post->ID
			: (is_admin() && !empty($admin_post) ? $admin_post : null);

		if($req_id !== null && $post_id !== null && $req_id !== $post_id)
		{
			$err = "req_id={$req_id}' is not equal to 'post_id={$post_id}";
			write_log($err);
			wp_die($err);
		}

		return $post_id ?: ($req_id ?: null);
	}
}





if ( ! function_exists('current_page_number')) {

	function current_page_number() : int {
		$page = 1;

		if(!empty(get_query_var('page')))
		{
			$page = get_query_var('page');
		}

		if(!empty(get_query_var('paged')))
		{
			$page = get_query_var('paged');
		}
		
		return (int) $page;

	}

}

if ( ! function_exists('write_log')) {
	
	if(! function_exists('var_error_log'))
	{
		function var_error_log( $object=null ){
			ob_start();
			var_dump( $object );
			$contents = ob_get_contents();
			ob_end_clean();
			return $contents;
		}
	}
	
	function write_log($log = '', $debug = false) : void {
		$separator = "**************************";
		$separator_start = sprintf("\n\n%sWRITE_LOG_START%s\n", $separator, $separator);
		$separator_end   = sprintf("\n%sWRITE_LOG_END%s\n\n", $separator, $separator);

		$log_type = gettype($log);

		if (is_array($log) || is_object($log)) {
			$log = print_r(var_error_log($log), true);
		}

		$output = sprintf(
			"%sURI = %s\nUSER_AGENT = %s\nIP_ADDRESS = %s\nTYPE = %s\nLOG MESSAGE = \n\n%s",
			$separator_start,
			$_SERVER['REQUEST_URI'] ?? '',
			$_SERVER['HTTP_USER_AGENT'] ?? '',
			function_exists('get_ip_address') ? get_ip_address() : '(unknown)',
			$log_type,
			$log
		);

		$post_for_log = $_POST;

		foreach(['CCNum', 'ExpMonth', 'ExpYear', 'CVV2'] as $sensitive)
		{
			unset($post_for_log[$sensitive]);
		}

		$output .= sprintf("\nPOST = %s", print_r($post_for_log, true));

		if ($debug === true) {
			// ---- NEW TRACE SECTION ----
			$trace  = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
			$subset = array_slice($trace, 1, 7);

			$lines = [];
			foreach ($subset as $i => $t) {
				$func = ($t['class'] ?? '') . ($t['type'] ?? '') . ($t['function'] ?? '');
				$file = $t['file'] ?? '(no-file)';
				$line = $t['line'] ?? 0;
				$lines[] = sprintf('#%d %s() @ %s:%d', $i, $func, $file, $line);
			}
			$output .= sprintf("\nTRACE:\n%s", implode("\n", $lines));
			// ---- END TRACE SECTION ----
		}

		$output .= $separator_end;

		error_log($output);
	}
}

function default_language() : string {
	static $cache = [];
	$cache_key = 'wp_core_default_language'; //constant

	if(array_key_exists($cache_key, $cache))
	{
		return $cache[$cache_key];
	}

	return $cache[$cache_key] = function_exists('pll_default_language')
		? pll_default_language()
		: explode('_', get_locale())[0];
}


function get_languages() : array {
	static $cache = [];
	$cache_key = 'wp_core_get_languages'; //constant

	if(array_key_exists($cache_key, $cache))
	{
		return $cache[$cache_key];
	}

	if(function_exists('pll_languages_list'))
	{
		$cache[$cache_key] = pll_languages_list();

		if(!empty($cache[$cache_key])) {
			return $cache[$cache_key];
		}
	}

	return $cache[$cache_key] = [explode('_', get_locale())[0]];
}


function current_language() : string {
	static $cache = [];
	$cache_key = 'wp_core_current_language'; //constant

	if(array_key_exists($cache_key, $cache))
	{
		return $cache[$cache_key];
	}

	$lang = function_exists('pll_current_language')
		? pll_current_language()
		: null;

	return $cache[$cache_key] = !empty($lang)
		? $lang
		: explode('_', get_locale())[0];
}

if(!function_exists('get_ip_address'))
{
	/**
	 * Return the normalized client IP, or an empty string if it cannot be trusted.
	 */
	function get_ip_address(): string
	{
		$ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));

		if(!filter_var($ip, FILTER_VALIDATE_IP)) {
			return '';
		}

		// Match the proxy policy used by cloudflare_ban_ip_address().
		$proxy_ranges = (array) apply_filters('dy_cloudflare_proxy_ranges', [
			'173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22',
			'141.101.64.0/18', '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20',
			'197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
			'104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22', '2400:cb00::/32',
			'2606:4700::/32', '2803:f800::/32', '2405:b500::/32', '2405:8100::/32',
			'2a06:98c0::/29', '2c0f:f248::/32'
		]);
		$packed_ip = inet_pton($ip);

		foreach($proxy_ranges as $cidr) {
			$parts = explode('/', (string) $cidr, 2);

			if(count($parts) !== 2 || !filter_var($parts[0], FILTER_VALIDATE_IP) || !ctype_digit($parts[1])) {
				continue;
			}

			$network = inet_pton($parts[0]);
			$bits = (int) $parts[1];

			if(strlen($network) !== strlen($packed_ip) || $bits > strlen($packed_ip) * 8) {
				continue;
			}

			$bytes = intdiv($bits, 8);
			$remaining = $bits % 8;

			if(substr($packed_ip, 0, $bytes) !== substr($network, 0, $bytes)) {
				continue;
			}

			if($remaining > 0) {
				$mask = (0xff << (8 - $remaining)) & 0xff;

				if((ord($packed_ip[$bytes]) & $mask) !== (ord($network[$bytes]) & $mask)) {
					continue;
				}
			}

			$ip = trim((string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''));

			if(!filter_var($ip, FILTER_VALIDATE_IP)) {
				return '';
			}

			$packed_ip = inet_pton($ip);
			break;
		}

		return inet_ntop($packed_ip);
	}
	
}

if(!function_exists('home_lang')) {
	function home_lang() : string {
		static $cache = [];
		$cache_key = 'wp_core_home_lang';
		$output = '';

		if(isset($cache[$cache_key]))
		{
			$output = $cache[$cache_key];
		}
		else
		{
			global $polylang;

			if(isset($polylang))
			{
				$path = '';
				$pll_url = pll_home_url();

				if(!empty($pll_url))
				{
					$current_language = pll_current_language();
					$parsed_url = wp_parse_url($pll_url);
					$path_arr = array_values(array_filter(explode('/', $path)));

					if(in_array($current_language, $path_arr))
					{
						$parsed_url['path'] = $current_language;
					}
				}

				$output = $parsed_url['scheme'] . '://'
					. $parsed_url['host']
					. (isset($parsed_url['port']) ? ':' . $parsed_url['port'] : '')
					. (isset($parsed_url['path']) ? $parsed_url['path'] : '')
					. (isset($parsed_url['query']) ? '?' . $parsed_url['query'] : '')
					. (isset($parsed_url['fragment']) ? '#' . $parsed_url['fragment'] : '');

				$output = normalize_url($output);
			}
			else
			{
				$output = home_url('/');
			}

			$cache[$cache_key] = $output;
		}

		return $output;
	}
}

if(!function_exists('whatsapp_number')) {
	function whatsapp_number() : string {

		$current_language = current_language();
		$default_language = default_language();

		$prefix = ($current_language === $default_language) ? '' : '_' . $current_language;
		$whatsapp_number = get_option('dy_whatsapp' . $prefix);

		return preg_replace('/[^0-9]+/', '', $whatsapp_number);
	}
}



if(!function_exists('whatsapp_button'))
{
	function whatsapp_button($label = '', $text = '') : string {
		$output = '';
		$number = whatsapp_number();

		if(intval($number) > 0)
		{
			if(empty($label))
			{
				$label = 'Whatsapp';
			}
		
			$output = '<a class="pure-button button-whatsapp" target="_blank"><span class="dashicons dashicons-whatsapp"></span> '.esc_html($label).'</a>';
		}

		return $output;
	}
}



if(!function_exists('get_inline_file'))
{
	function get_inline_file($dir) : string {
		ob_start();
		require_once($dir);
		$output = ob_get_contents();
		ob_end_clean();
		return (string) $output;	
	}
}

if(!function_exists('load_picker_scripts'))
{
    function load_picker_scripts($plugin_dir_url, $dirname_file) : void {
        wp_enqueue_script( 'picker-js', $plugin_dir_url . 'js/picker/picker.js', array('jquery'), '3.6.2', true);
        wp_enqueue_script( 'picker-date-js', $plugin_dir_url . 'js/picker/picker.date.js', array('jquery', 'picker-js'), '3.6.2', true);
        wp_enqueue_script( 'picker-time-js', $plugin_dir_url . 'js/picker/picker.time.js',array('jquery', 'picker-js'), '3.6.2', true);	
        wp_enqueue_script( 'picker-legacy', $plugin_dir_url . 'js/picker/legacy.js', array('jquery', 'picker-js'), '3.6.2', true);

        $picker_translation = 'js/picker/translations/'.get_locale().'.js';
                
        if(file_exists($dirname_file.'/'.$picker_translation))
        {
            wp_enqueue_script( 'picker-time-translation', $plugin_dir_url.$picker_translation, array('jquery', 'picker-js'), '3.6.2', true);
        }	
        //picker end  
    }
}

if(!function_exists('load_picker_styles'))
{
	function load_picker_styles($plugin_dir_url) : void {
		wp_enqueue_style( 'picker-css', $plugin_dir_url . 'css/picker/default.css', array(), '', 'all' );
		wp_enqueue_style( 'picker-date-css', $plugin_dir_url . 'css/picker/default.date.css', array(), '', 'all' );
		wp_enqueue_style( 'picker-time-css', $plugin_dir_url . 'css/picker/default.time.css', array(), '', 'all' );		
	}
}

if(!function_exists('wrap_money_full'))
{
	function wrap_money_full($amount, $decimal = '.', $thousands = ',') : string {
		return currency_symbol() . money($amount, $decimal, $thousands) . ' ' . currency_name();
	}
}

if(!function_exists('wrap_money'))
{
	function wrap_money($amount, $decimal = '.', $thousands = ',') : string {
		return currency_symbol() . money($amount, $decimal, $thousands);
	}
}

if(!function_exists('money'))
{
	function money($amount,  $decimal = '.', $thousands = ',') : string {
		return number_format((float) $amount, 2, $decimal, $thousands);
	}
}

// New version that shows NO decimals
if (!function_exists('money_rounded')) {
	function money_rounded($amount, $thousands = ',') : string {
		return number_format(round((float) $amount), 0, '', $thousands);
	}
}

// Optionally, you can wrap it too:
if (!function_exists('wrap_money_rounded')) {
	function wrap_money_rounded($amount, $thousands = ',') : string {
		return currency_symbol() . money_rounded($amount, $thousands) . ' ' . currency_name();
	}
}

if(!function_exists('currency_symbol'))
{
	function currency_symbol() : string {
		return '$';
	}
}

if(!function_exists('currency_name'))
{
	function currency_name() : string {
		return 'USD';
	}
}

if (!function_exists('detect_date_format')) {
	function detect_date_format(string|null $str): int|null {


		if($str === null || $str === '') {
			return null;
		}

		static $cache = [];

		if (array_key_exists($str, $cache)) {
			return $cache[$str];
		}

		$formats = [
			1 => 'Y-m-d',
			2 => 'Y-m-d H:i:s',
		];

		foreach ($formats as $type => $format) {
			$date_time = DateTime::createFromFormat($format, $str);

			if ($date_time !== false && $date_time->format($format) === $str) {
				return $cache[$str] = $type;
			}
		}

		return $cache[$str] = null;
	}
}

if (!function_exists('is_valid_date')) {
	function is_valid_date(string|null $date) : bool {
		return detect_date_format($date) !== null;
	}
}
if (!function_exists('is_valid_short_date')) {
	function is_valid_short_date(string|null $date, array $formats = ['Y-m-d']) : bool {
		return detect_date_format($date) === 1;
	}
}

if (!function_exists('is_valid_long_date')) {
	function is_valid_long_date(string|null $date, array $formats = ['Y-m-d H:i:s']) : bool {
		return detect_date_format($date) === 2;
	}
}


if(!function_exists('is_valid_time'))
{
	function is_valid_time($str) : bool {
		static $cache = [];

		if(empty($str))
		{
			return false;
		}

		if(array_key_exists($str, $cache))
		{
			return $cache[$str];
		}

		// 12-hour format: h:i A  (e.g. "03:45 PM")
		return $cache[$str] = (bool) preg_match('/^(?:0?[1-9]|1[0-2]):[0-5][0-9] (?:AM|PM)$/', $str);
	}
}

if(!function_exists('hour_to_seconds')) {
	function hour_to_seconds(string $time): int|false
	{
		$time = trim($time);

		if(
			$time === '' ||
			strlen($time) > 10 ||
			preg_match('/^(0?[1-9]|1[0-2]):([0-5][0-9])\s?(AM|PM)$/i', $time, $match) !== 1
		) {
			return false;
		}

		$hour = (int) $match[1];
		$minute = (int) $match[2];
		$period = strtoupper($match[3]);

		if($hour === 12) {
			$hour = 0;
		}

		if($period === 'PM') {
			$hour += 12;
		}

		return ($hour * 3600) + ($minute * 60);
	}
}

if(!function_exists('is_hour_in_range')) {
	/**
	 * @param array{0?: string, 1?: string} $range
	 */
	function is_hour_in_range(string $hour_string, array $range): bool
	{
		$min_hour_string = $range[0] ?? '';
		$max_hour_string = $range[1] ?? '';

		if(
			!is_string($min_hour_string)
			|| !is_string($max_hour_string)
			|| !is_valid_time($hour_string)
		) {
			return false;
		}

		$hour_seconds = hour_to_seconds($hour_string);

		if($hour_seconds === false) {
			return false;
		}

		if($min_hour_string !== '') {
			$min_hour_seconds = hour_to_seconds($min_hour_string);

			if($min_hour_seconds === false || $hour_seconds < $min_hour_seconds) {
				return false;
			}
		}

		if($max_hour_string !== '') {
			$max_hour_seconds = hour_to_seconds($max_hour_string);

			if($max_hour_seconds === false || $hour_seconds > $max_hour_seconds) {
				return false;
			}
		}

		return true;
	}
}


if(!function_exists('is_in_theme'))
{
	function is_in_theme() : bool {
		$path = dirname(__FILE__);
		
		// Check if the script is in a theme directory
		$theme_dir = WP_CONTENT_DIR . '/themes/';
		$theme_dir = str_replace('\\', '/', $theme_dir); // Windows fix
		if (strpos($path, $theme_dir) === 0) {
			return true;
		}
	
		return false;
	}	
}

if(!function_exists('get_site_time'))
{
	function get_site_time() : array {
        $timezone = get_option('timezone_string');

        if (empty($timezone)) {
            $timezone = 'UTC';
        }

        $datetime_zone = new DateTimeZone($timezone);
        $utc_offset_seconds = $datetime_zone->getOffset(new DateTime());
        $utc_offset_hours = floor($utc_offset_seconds / 3600);
        $utc_offset_minutes = abs(($utc_offset_seconds % 3600) / 60);
        $utc_offset = sprintf('%+03d:%02d', $utc_offset_hours, $utc_offset_minutes);

		return [
			'site_timezone' => $timezone,
			'site_offset' => $utc_offset,
			'site_timestamp' => round(microtime(true) * 1000)
		];

	}
}

if ( ! function_exists( 'dy_format_blocks' ) ) {
	function dy_format_blocks( $raw_blocks = '', $format = 'html' ) : string{

		// Valid formats
		$valid_formats = [ 'html', 'text' ];

		// Check format
		if ( ! in_array( $format, $valid_formats, true ) ) {
			write_log(
				sprintf(
					'Invalid format "%s". Valid formats are: %s',
					esc_html( $format ),
					implode( ', ', $valid_formats )
				)
			);

			return '';
		}

		// If no blocks passed, return empty string
		if ( empty( $raw_blocks ) ) {
			return '';
		}

		$output = [];
		$blocks = parse_blocks( $raw_blocks );

		foreach ( $blocks as $block ) {
			$parsed_block = trim(do_shortcode(render_block( $block )));

			if(empty($parsed_block)) continue;

			if ( $format === 'html' ) {
				$output[] = $parsed_block;
			} elseif ( $format === 'text' ) {
				$parsed_text = html_to_plain_text( $parsed_block );

				if(empty($parsed_text)) continue;

				$output[] = $parsed_text;
			}
		}

		return (count($output) > 0) ? implode("\n\n", $output) : '';
	}
}


if(!function_exists('html_to_plain_text')) {
	function html_to_plain_text($html) : string {
		$html = strip_shortcodes($html);

		// --- Convert <table> to Markdown before other replacements ---
		$html = preg_replace_callback('/<table.*?>(.*?)<\/table>/is', function($matches) {
			$tableHtml = $matches[1];

			// Find all rows
			preg_match_all('/<tr.*?>(.*?)<\/tr>/is', $tableHtml, $rowMatches);
			$rows = [];
			foreach ($rowMatches[1] as $rowHtml) {
				// Find all cells (th or td)
				preg_match_all('/<(td|th)[^>]*>(.*?)<\/\1>/is', $rowHtml, $cellMatches);
				$cells = array_map(function($c) {
					$text = trim(strip_tags($c));
					// Replace | and - with /
					$text = str_replace(['|','-'], '/', $text);
					return $text;
				}, $cellMatches[2]);
				if (!empty($cells)) {
					$rows[] = $cells;
				}
			}

			if (empty($rows)) return '';

			// First row is header
			$header = array_shift($rows);
			$colCount = count($header);

			// Markdown table
			$md  = '| ' . implode(' | ', $header) . " |\n";
			$md .= '| ' . implode(' | ', array_fill(0, $colCount, '---')) . " |\n";
			foreach ($rows as $r) {
				$r = array_pad($r, $colCount, '');
				$md .= '| ' . implode(' | ', $r) . " |\n";
			}

			return "\n" . $md . "\n";
		}, $html);

		// --- Your existing rules ---
		$search = [
			'/\[javascript protected email address\]/i',
			'/<br\s*\/?>/i',
			'/<\/?p[^>]*>/i',
			'/<li[^>]*>/i',
			'/<\/li>/i',
			'/<\/?ol[^>]*>/i',
			'/<\/?ul[^>]*>/i',
			'/<h[1-6][^>]*>(.*?)<\/h[1-6]>/is',
			'/<b[^>]*>(.*?)<\/b>/is',
    		'/<strong[^>]*>(.*?)<\/strong>/is',
		];

		$replace = [
			"\n",           // [javascript protected email address]
			"\n",           // <br> → salto de línea
			"\n",           // <p> o </p> → salto de línea
			"\n- ",         // <li>
			"",             // </li>
			"\n",           // <ol> o </ol>
			"\n",           // <ul> o </ul>
			"**$1**:\n",    // <h1>…</h1> → **…**:
			"**$1**",       // <b>…</b> → **…**
			"**$1**",       // <strong>…</strong> → **…**
		];

		$text = preg_replace($search, $replace, $html);
		$text = wp_strip_all_tags($text);

		while (strpos($text, "\n\n\n") !== false) {
			$text = str_replace("\n\n\n", "\n\n", $text);
		}

		return trim($text);
	}
}


if(!function_exists('dy_strtotime'))
{
	/**
	 * Convert a date/time string to a Unix timestamp using the
	 * WordPress site's configured timezone.
	 *
	 * This works similarly to PHP's strtotime(), but ensures that
	 * date/time strings without an explicit timezone are interpreted
	 * using the timezone configured in WordPress.
	 *
	 * Examples:
	 *
	 * dy_strtotime('2026-08-25 10:30:00');
	 * dy_strtotime('tomorrow');
	 * dy_strtotime('+2 days');
	 * dy_strtotime('next monday');
	 *
	 * Note:
	 * If the provided string contains an explicit timezone or UTC offset,
	 * PHP will honor that timezone instead of the WordPress timezone.
	 *
	 * @param string $str Date/time string accepted by PHP's DateTime parser.
	 *
	 * @return int Unix timestamp.
	 *
	 * @throws InvalidArgumentException If $str is empty (after trimming).
	 * @throws Exception If PHP cannot parse the supplied date/time.
	 */
	function dy_strtotime($str) : int {

		if(!is_string($str) || trim($str) === '') {
			throw new InvalidArgumentException(
				'Param $str must be a non-empty string in dy_strtotime.'
			);
		}

		/*
		 * wp_timezone() returns the site's timezone as a DateTimeZone object.
		 *
		 * It correctly supports both:
		 *
		 * - Named timezones such as "America/Panama"
		 * - UTC offsets configured through WordPress
		 *
		 * This is preferable to manually reading timezone_string or gmt_offset.
		 */
		$timezone = wp_timezone();

		/*
		 * Interpret the supplied date/time using the WordPress timezone.
		 *
		 * DateTimeImmutable is used because the date object itself does not
		 * need to be modified after creation.
		 */
		$datetime = new DateTimeImmutable(
			$str,
			$timezone
		);

		/*
		 * getTimestamp() returns an actual integer.
		 *
		 * Avoid using format('U') here because format() always returns
		 * a string.
		 */
		return $datetime->getTimestamp();
	}
}


if(!function_exists('dy_date'))
{
	/**
	 * Format a Unix timestamp using the WordPress site's configured timezone.
	 *
	 * The timestamp is treated as an absolute UTC instant and converted to
	 * the site timezone (via wp_timezone()) before formatting. If no
	 * timestamp is given, the current time is used.
	 *
	 * @param string   $format    A valid PHP date() format string. Must be non-empty.
	 * @param int|null $timestamp Unix timestamp to format, or null to use the
	 *                            current time. Defaults to null.
	 *
	 * @return string The formatted date/time string.
	 *
	 * @throws InvalidArgumentException If $format is an empty string.
	 */

	function dy_date($format, $timestamp = null) {

		if(!is_string($format) || $format === '') {
			throw new InvalidArgumentException(
				'Param $format must be a non-empty string in dy_date.'
			);
		}

		/*
		 * Use the current Unix timestamp when none is explicitly provided.
		 *
		 * The strict null comparison is intentional because timestamp 0
		 * is valid and represents 1970-01-01 00:00:00 UTC.
		 */
		if($timestamp === null) {
			$timestamp = time();
		}

		/*
		 * Accept integer timestamps directly.
		 *
		 * Numeric strings are also accepted for compatibility with values
		 * commonly retrieved from databases, options, metadata, or APIs.
		 */
		if(!is_int($timestamp)) {

			if(is_numeric($timestamp)) {
				$timestamp = (int) $timestamp;
			}
			else {
				throw new InvalidArgumentException(
					'Param $timestamp must be an integer in dy_date.'
				);
			}
		}

		/*
		 * Prefixing a Unix timestamp with "@" creates the DateTime object
		 * as an absolute UTC instant.
		 *
		 * A Unix timestamp itself has no timezone. The timezone only affects
		 * how that timestamp is displayed.
		 */
		$datetime = new DateTimeImmutable('@' . $timestamp);

		/*
		 * Convert the absolute timestamp to the WordPress site's timezone
		 * before formatting it.
		 */
		return $datetime
			->setTimezone(wp_timezone())
			->format($format);
	}
}

if(!function_exists('normalize_url')) {
	function normalize_url($url) : string {
		return preg_replace('#(?<!:)/{2,}#', '/', $url);
	}
}

if(!function_exists('implode_last')) {
	//Join values with commas, except use a custom separator before the last value.
	
	function implode_last(array $arr, string $last_separator = 'and', string $item_prefix = ''): string
	{
		$values = array_map(function($val) use ($item_prefix){
			$val = strval($val);

			return (empty($item_prefix)) ? $val : "{$item_prefix} {$val}";
		}, array_values($arr));
		$count  = count($values);

		if ($count === 0) {
			return '';
		}

		if ($count === 1) {
			return $values[0];
		}

		if ($count === 2) {
			return $values[0] . ' ' . $last_separator . ' ' . $values[1];
		}

		$last  = array_pop($values);
		$front = implode(', ', $values);

		return $front . ' ' . $last_separator . ' ' . $last;
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

if(!function_exists('luhn_check')) {
	function luhn_check($number) : bool {
		$number = preg_replace('/[\s-]/', '', (string) $number);

		if ($number === '' || !ctype_digit($number)) {
			return false;
		}

		$length = strlen($number);
		$parity = $length % 2;
		$total = 0;

		for ($i = 0; $i < $length; $i++) {
			$digit = (int) $number[$i];

			if ($i % 2 === $parity) {
				$digit *= 2;

				if ($digit > 9) {
					$digit -= 9;
				}
			}

			$total += $digit;
		}

		return $total % 10 === 0;
	}
}

if (!function_exists('str_row_to_array')) {
	function str_row_to_array(
		string $str,
		int|null $items_limit = null,
		Closure|string $sanitize_func = 'sanitize_text_field'
	): array {
		if (!$str) {
			return [];
		}

		if (!is_callable($sanitize_func)) {
			$sanitize_func = 'sanitize_text_field';
		}

		$str = str_replace(
			["\r\n", "\r"],
			"\n",
			html_entity_decode((string) $str)
		);

		$items = array_map('trim', explode("\n", $str));
		$items = array_map($sanitize_func, $items);
		$items = array_values(array_unique(array_filter($items)));

		return (is_int($items_limit) && $items_limit > 0) 
			? array_slice($items, 0, $items_limit)
			: $items;
	}
}

if (!function_exists('email_str_row_to_array')) {
	function email_str_row_to_array(
		string $str,
		int|null $recipients_limit = null
	): array {
		return str_row_to_array(
			$str,
			$recipients_limit,
			'sanitize_email'
		);
	}
}

if(!function_exists('is_safe_json')) {
	function is_safe_json(string $jsonString): bool
	{
		if (trim($jsonString) === '') {
			return false;
		}

		if (function_exists('json_validate')) {
			return json_validate($jsonString);
		}

		// Fallback for PHP < 8.3
		json_decode($jsonString);
		return json_last_error() === JSON_ERROR_NONE;
	}
}


?>
