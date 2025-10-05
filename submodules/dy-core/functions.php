<?php

if ( !defined( 'WPINC' ) ) exit;

define('DY_CORE_FUNCTIONS', true);


if(! function_exists('get_dy_id'))
{
	function get_dy_id()
	{
		global $post;
		$req_id = null;
		$post_id = null;
		$admin_id = null;

		if(!empty(secure_request('dy_id')))
		{
			$req_id = (int) secure_request('dy_id');
		} else {
			if(!empty(secure_request('post_id'))) {
				$req_id = (int) secure_request('post_id');
			}
		}

		if($post instanceof WP_Post)
		{
			$post_id = $post->ID;
		}
		else
		{
			$post_id = (is_admin() && !empty(secure_request('post'))) ? (int) secure_request('post') : $post_id;
		}

		if($req_id !== null && $post_id !== null && $req_id !== $post_id)
		{
			$err = "req_id={$req_id}' is not equal to 'post_id={$post_id}";
			write_log($err);
			wp_die($err);
		}

		if($post_id) return $post_id;
		else if($req_id) return $req_id;
		else return null;
	}
}


if ( ! function_exists('current_page_number')) {

	function current_page_number()
	{
		$page = 1;

		if(!empty(get_query_var('page')))
		{
			$page = get_query_var('page');
		}

		if(!empty(get_query_var('paged')))
		{
			$page = get_query_var('paged');
		}
		
		return $page;

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
	
	function write_log($log = '') {
		$separator = "**************************";
		$separator_start = "\n\n" . $separator . 'WRITE_LOG_START' . $separator . "\n";
		$separator_end = "\n" . $separator . 'WRITE_LOG_END' . $separator . "\n\n";

		$output = $separator_start
			. "URI = " . ($_SERVER['REQUEST_URI'] ?? '') 
			. "\nUSER_AGENT = " . ($_SERVER['HTTP_USER_AGENT'] ?? '')
			. "\nIP_ADDRESS = " . (function_exists('get_ip_address') ? get_ip_address() : '(unknown)')
			. "\nTYPE = " . gettype($log);

		if (isset($_POST) && is_array($_POST) && !empty($_POST)) {
			// remove sensitive fields
			foreach (['CCNum', 'ExpMonth', 'ExpYear', 'CVV2'] as $sensitive) {
				if (isset($_POST[$sensitive])) {
					unset($_POST[$sensitive]);
				}
			}
			$output .= "\nPOST = " . json_encode($_POST);
		}

		$output .= "\nLOG = ";

		if (is_array($log) || is_object($log)) {
			$log = print_r(var_error_log($log), true);
		}

		$output .= "\n\n" . $log;

		// ---- NEW TRACE SECTION ----
		$trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
		$subset = array_slice($trace, 0, 3); // skip #0 and take 3 frames
		$lines = [];

		foreach ($subset as $i => $t) {
			$func = ($t['class'] ?? '') . ($t['type'] ?? '') . ($t['function'] ?? '');
			$file = $t['file'] ?? '(no-file)';
			$line = $t['line'] ?? 0;
			$lines[] = sprintf('#%d %s() @ %s:%d', $i, $func, $file, $line);
		}
		$output .= "\nTRACE:\n" . implode("\n", $lines);
		// ---- END TRACE SECTION ----

		$output .= $separator_end;

		error_log($output);
	}

}




if(!function_exists('default_language'))
{
	function default_language()
	{
		$which_var = 'wp_core_default_language';
		global $$which_var;
		global $polylang;
		$lang = '';

		if(isset($$which_var))
		{
			$lang = $$which_var;
		}
		else
		{
			if(isset($polylang))
			{
				$lang = pll_default_language();
			}
			else
			{
				$locale_str = get_locale();
				$lang = $locale_str;
			
				if(strlen($locale_str) === 5)
				{
					$lang = substr($locale_str, 0, -3);
				}
			}

			$GLOBALS[$which_var] = $lang;
		}

		return $lang;
	}
}


if(!function_exists('get_languages'))
{
	function get_languages()
	{
		global $polylang;
		$output = [];
		$which_var = 'wp_core_get_languages';
		global $$which_var;

		if(isset($$which_var))
		{
			$output = $$which_var;
		}
		else
		{
			if(isset($polylang))
			{
				$languages = PLL()->model->get_languages_list();

				for($x = 0; $x < count($languages); $x++)
				{
					foreach($languages[$x] as $key => $value)
					{
						if($key == 'slug')
						{
							array_push($output, $value);
						}
					}	
				}
			}

			if(count($output) === 0)
			{
				$locale_str = get_locale();

				if(strlen($locale_str) === 5)
				{
					array_push($output, substr($locale_str, 0, -3));
				}
				else if(strlen($locale_str) === 2)
				{
					array_push($output, $locale_str);
				}
			}

			$GLOBALS[$which_var] = $output;
		}


		return $output;
	}	
}

if(!function_exists('current_language'))
{
	function current_language($the_id = '')
	{
		global $polylang;
		global $post;
		$output = '';
		$which_var = 'wp_core_current_language_' . $the_id;

		global $$which_var;

		if($$which_var)
		{
			$output = $$which_var;
		}
		else
		{
			if(isset($polylang))
			{
				$lang = pll_current_language();

				if($lang)
				{
					$output = $lang;
				}
			}

			if(empty($output))
			{
				$locale = get_locale();
				$locale_strlen = strlen($locale);

				if($locale_strlen === 5)
				{
					$output = substr($locale, 0, -3);
				}
				if($locale_strlen === 2)
				{
					$output = $locale;
				}
			}

			$GLOBALS[$which_var] = $output;
		}


		return $output;
	}
}

if(!function_exists('get_ip_address'))
{
	function get_ip_address()
	{
		return (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) ? $_SERVER['HTTP_CF_CONNECTING_IP'] : $_SERVER['REMOTE_ADDR'];
	}
	
}

if(!function_exists('home_lang'))
{
    function home_lang()
    {
        $which_var = 'wp_core_home_lang';
        global $$which_var;
        $output = '';

        if(isset($$which_var))
        {
            $output = $$which_var;
        }
        else
        {
            global $polylang;

            if($polylang)
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
                $output =  home_url('/');
            }

            $GLOBALS[$which_var] = $output;
        }

        return $output;
    }
}


if(!function_exists('whatsapp_button'))
{
	function whatsapp_button($label = '', $text = '')
	{
		$output = '';
		$number = apply_filters('dy_whatsapp_number', '');

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


if(!function_exists('validate_recaptcha'))
{
	function validate_recaptcha()
	{

		$g_recaptcha_response = secure_post('g-recaptcha-response');

		if(empty($g_recaptcha_response))
		{
			return false;
		}

		$secret_key = get_option('dy_recaptcha_secret_key');

		if(!$secret_key)
		{
			return false;
		}

		$which_var = 'dy_valid_recaptcha';
		global $$which_var;

		if(isset($$which_var))
		{
			return $$which_var;
		}

		$output = false;
		$url = 'https://www.google.com/recaptcha/api/siteverify';

		$ip = (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) 
			? $_SERVER['HTTP_CF_CONNECTING_IP'] : 
			$_SERVER['REMOTE_ADDR'];

		$params = array(
			'secret' => $secret_key,
			'remoteip' => $ip,
			'response' => $g_recaptcha_response,
		);

		$resp = wp_remote_post($url, array(
			'body' => $params
		));

		if ( is_array( $resp ) && ! is_wp_error( $resp ) )
		{
			if($resp['response']['code'] === 200)
			{
				$data = json_decode($resp['body'], true);

				if($data['success'] === true)
				{
					$output = true;
				}
				else
				{
					$GLOBALS['dy_request_invalids'] = array(__('Invalid Recaptcha'));

					if(array_key_exists('error-codes', $data))
					{
						$errors = $data['error-codes'];

						if(in_array('invalid-input-response', $errors))
						{
							cloudflare_ban_ip_address($errors);
						}
					}
				}
			}
		}

		$GLOBALS[$which_var] = $output;

		return $output;
	}
}

if(!function_exists('get_inline_file'))
{
	function get_inline_file($dir)
	{
		ob_start();
		require_once($dir);
		$output = ob_get_contents();
		ob_end_clean();
		return $output;	
	}
}

if(!function_exists('load_picker_scripts'))
{
    function load_picker_scripts($plugin_dir_url, $dirname_file)
    {
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
	function load_picker_styles($plugin_dir_url)
	{
		wp_enqueue_style( 'picker-css', $plugin_dir_url . 'css/picker/default.css', array(), '', 'all' );
		wp_enqueue_style( 'picker-date-css', $plugin_dir_url . 'css/picker/default.date.css', array(), '', 'all' );
		wp_enqueue_style( 'picker-time-css', $plugin_dir_url . 'css/picker/default.time.css', array(), '', 'all' );		
	}
}

if(!function_exists('wrap_money_full'))
{
	function wrap_money_full($amount, $decimal = '.', $thousands = ',')
	{
		return currency_symbol() . money($amount, $decimal, $thousands) . ' ' . currency_name();
	}
}

if(!function_exists('wrap_money'))
{
	function wrap_money($amount, $decimal = '.', $thousands = ',')
	{
		return currency_symbol() . money($amount, $decimal, $thousands);
	}
}

if(!function_exists('money'))
{
	function money($amount,  $decimal = '.', $thousands = ',')
	{
		return number_format((float) $amount, 2, $decimal, $thousands);
	}
}

if(!function_exists('currency_symbol'))
{
	function currency_symbol()
	{
		return '$';
	}
}

if(!function_exists('currency_name'))
{
	function currency_name()
	{
		return 'USD';
	}
}

if (!function_exists('is_valid_date')) {
    function is_valid_date($str)
    {
        if (empty($str)) {
            return false;
        }
        
        // Generate a cache key from the sanitized input
        $cacheKey = sanitize_key($str) . '_is_valid_date';
        
        // Check global cache
        if (isset($GLOBALS[$cacheKey])) {
            return $GLOBALS[$cacheKey];
        }
        
        // List of allowed formats
        $formats = ['Y-m-d', 'Y-m-d H:i:s'];
        $valid = false;
        
        foreach ($formats as $format) {
            $dateTime = DateTime::createFromFormat($format, $str);
            // Check for valid parsing and exact match (to avoid partially valid strings)
            if ($dateTime !== false && $dateTime->format($format) === $str) {
                $valid = true;
                break;
            }
        }
        
        // Cache the result globally
        $GLOBALS[$cacheKey] = $valid;
        
        return $valid;
    }
}


if(!function_exists('is_valid_time'))
{
	function is_valid_time($str)
	{
		$output = false;

		if(!empty($str))
		{
			$which_var = $str.'_is_valid_time';
			global $$which_var;
			
			if(isset($$which_var))
			{
				$output = $$which_var;
			}
			else
			{
				if(DateTime::createFromFormat('H:i A', $str) !== false)
				{
					$output = true;
				}

				$GLOBALS[$which_var] = $output;
			}
		}
				
		return $output;
	}
}

if(!function_exists('is_in_theme'))
{
	function is_in_theme() {
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

if(!function_exists('is_in_plugin'))
{
	function is_in_plugin() {
		$path = dirname(__FILE__);
		
		// Check if the script is in a plugin directory
		$plugin_dir = WP_CONTENT_DIR . '/plugins/';
		$plugin_dir = str_replace('\\', '/', $plugin_dir); // Windows fix
		if (strpos($path, $plugin_dir) === 0) {
			return true;
		}
	
		return false;
	}	
}

if(!function_exists('get_site_time'))
{
	function get_site_time()
	{
        $timezone = get_option('timezone_string');

        if (empty($timezone)) {
            $timezone = 'UTC';
        }

        $datetime_zone = new DateTimeZone($timezone);
        $utc_offset_seconds = $datetime_zone->getOffset(new DateTime());
        $utc_offset_hours = floor($utc_offset_seconds / 3600);
        $utc_offset_minutes = abs(($utc_offset_seconds % 3600) / 60);
        $utc_offset = sprintf('%+03d:%02d', $utc_offset_hours, $utc_offset_minutes);

		return array(
			'site_timezone' => $timezone,
			'site_offset' => $utc_offset,
			'site_timestamp' => round(microtime(true) * 1000)
		);

	}
}

if ( ! function_exists( 'dy_format_blocks' ) ) {
	function dy_format_blocks( $raw_blocks = '', $format = 'html' ) {

		// Valid formats
		$valid_formats = [ 'html', 'text' ];

		// Check format
		if ( ! in_array( $format, $valid_formats, true ) ) {
			wp_die(
				sprintf(
					'Invalid format "%s". Valid formats are: %s',
					esc_html( $format ),
					implode( ', ', $valid_formats )
				)
			);
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

		return implode("\n\n", $output);
	}
}


if(!function_exists('html_to_plain_text')) {
	function html_to_plain_text($html) {
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
	function dy_strtotime($str) {
		// This function behaves a bit like PHP's StrToTime() function, but taking into account the Wordpress site's timezone
		// CAUTION: It will throw an exception when it receives invalid input - please catch it accordingly
		// From https://mediarealm.com.au/

		$tz_string = get_option('timezone_string');
		$tz_offset = get_option('gmt_offset', 0);

		if (!empty($tz_string))
		{
			// If site timezone option string exists, use it
			$timezone = $tz_string;
		}
		else if ($tz_offset == 0)
		{
			// get UTC offset, if it isn’t set then return UTC
			$timezone = 'UTC';
		}
		else
		{
			$timezone = $tz_offset;

			if(substr($tz_offset, 0, 1) != "-" && substr($tz_offset, 0, 1) != "+" && substr($tz_offset, 0, 1) != "U")
			{
				$timezone = "+" . $tz_offset;
			}
		}
		
		$datetime = new DateTime($str, new DateTimeZone($timezone));

		return $datetime->format('U');
	}
}

if(!function_exists('dy_date'))
{
	function dy_date($format, $timestamp = null) {
		// This function behaves a bit like PHP's Date() function, but taking into account the Wordpress site's timezone
		// CAUTION: It will throw an exception when it receives invalid input - please catch it accordingly
		// From https://mediarealm.com.au/

		$tz_string = get_option('timezone_string');
		$tz_offset = get_option('gmt_offset', 0);

		if (!empty($tz_string)) 
		{
			// If site timezone option string exists, use it
			$timezone = $tz_string;
		} 
		elseif ($tz_offset == 0) 
		{
				// get UTC offset, if it isn’t set then return UTC
				$timezone = 'UTC';
		} else {
			$timezone = $tz_offset;

			if(substr($tz_offset, 0, 1) != "-" && substr($tz_offset, 0, 1) != "+" && substr($tz_offset, 0, 1) != "U") {
				$timezone = "+" . $tz_offset;
			}
		}

		if($timestamp === null) {
			$timestamp = time();
		}

		$datetime = new DateTime();
		$datetime->setTimestamp($timestamp);
		$datetime->setTimezone(new DateTimeZone($timezone));
		return $datetime->format($format);
	}
}

if(!function_exists('normalize_url')) {
	function normalize_url($url) {
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



?>