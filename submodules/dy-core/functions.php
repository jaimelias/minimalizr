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

		if(isset($_REQUEST['dy_id']))
		{
			$req_id = intval(sanitize_text_field($_REQUEST['dy_id']));
		}

		if(isset($post))
		{
			$post_id = $post->ID;
		}
		else
		{
			if(is_admin() && isset($_GET['post']))
			{
				$post_id = intval(sanitize_text_field($_REQUEST['post']));
			}
		}

		if($req_id !== null && $post_id !== null && $req_id !== $post_id)
		{
			throw new Exception("'req_id: $req_id' is not equal to 'post_id: $post_id'");
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
	
	function write_log ( $log )  {
		
		$separator = "**************************";
		$separator_start = "\n\n" .$separator . 'WRITE_LOG_START' . $separator . "\n";
		$separator_end = "\n" .$separator . 'WRITE_LOG_END' . $separator. "\n\n";
		$output = $separator_start . "URI = " . $_SERVER['REQUEST_URI'] . "\nUSER_AGENT = " . $_SERVER['HTTP_USER_AGENT'] . "\nIP_ADDRESS = " . get_ip_address() .  "\nTYPE = " . gettype($log);
		if(isset($_POST))
		{
			if(is_array($_POST))
			{
				if(!empty($_POST))
				{

					if(isset($_POST['CCNum']))
					{
						unset($_POST['CCNum']);
					}

					if(isset($_POST['ExpMonth']))
					{
						unset($_POST['ExpMonth']);
					}
					
					if(isset($_POST['ExpYear']))
					{
						unset($_POST['ExpYear']);
					}
					
					if(isset($_POST['CVV2']))
					{
						unset($_POST['CVV2']);
					}
					

					$output .= "\nPOST = " .json_encode($_POST);
				}
			}
		}

		$output .= "\nLOG = ";
		
		if ( is_array( $log ) || is_object( $log ) ) {

			$log = print_r(var_error_log($log), true);
		}


		$output .= "\n\n" . $log . $separator_end;



		error_log( $output );


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
		$output = array();
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
	function current_language($value = '')
	{
		global $polylang;
		$output = '';
		$which_var = 'wp_core_current_language';
		global $$which_var;

		if($$which_var)
		{
			$output = $$which_var;
		}
		else
		{
			if(isset($polylang))
			{
				//$value === name, locale or slug
				$lang = (empty($value)) ? pll_current_language() : pll_current_language($value);

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


if(!function_exists('cloudflare_ban_ip_address'))
{
	function cloudflare_ban_ip_address($ban_message = ''){

		$output = false;
		$dy_cloudflare_api_token = get_option('dy_cloudflare_api_token');
		
		if(!empty($dy_cloudflare_api_token))
		{
			
			$url = 'https://api.cloudflare.com/client/v4/user/firewall/access_rules/rules';
			
			$ip = get_ip_address();
			
			if(!isset($_SERVER['HTTP_CF_CONNECTING_IP']))
			{
				if($_SERVER['SERVER_NAME'] !== 'localhost')
				{
					$admin_email = get_option('admin_email');
					$email_message = 'Cloudflare WAF is not Enabled in: ' . get_bloginfo('name');
					$headers = array('Content-Type: text/html; charset=UTF-8');

					wp_mail($admin_email, $email_message, $email_message, $headers);
				}
			}

			$headers = array(
				'Authorization' => 'Bearer ' . sanitize_text_field($dy_cloudflare_api_token),
				'Content-Type' => 'application/json'
			);

			$data = array(
				'mode' => 'block',
				'configuration' => array('target' => 'ip', 'value' => $ip),
				'notes' => 'Banned on '.date('Y-m-d H:i:s').' by PHP-script'
			);

			if(!empty($ban_message))
			{
				$data['notes'] .= ' | ' . is_array($ban_message) ? implode(', ', $ban_message): $ban_message;
			}


			$resp = wp_remote_post($url, array(
				'headers' => $headers,
				'body' => json_encode($data),
				'data_format' => 'body'
			));
			
			if ( is_array( $resp ) && ! is_wp_error( $resp ) )
			{
				$code = $resp['response']['code'];
				$data = json_decode($resp['body'], true);
				$messages = (array_key_exists('messages', $data)) ? $data['messages'] : null;
				$errors = (array_key_exists('error', $data)) ? $data['errors'] : null;

				$log = array(
					'messages' => $messages,
					'errors' => $errors,
					'ip' => $ip,
					'ban_message' => $ban_message
				);

				$log = json_encode($log);

				if($code === 200)
				{
					if($data['success'])
					{
						write_log('Cloudflare WAF Banned IP: '. $log);
						$output = true;
					}
					else
					{
						write_log('Cloudflare WAF Error: '. $log);
					}
				}
				else
				{
					write_log('Cloudflare WAF Error: ' . $log);
				}
			}
			else
			{
				write_log('Unknown Cloudflare Error');
			}
		}

		return $output;
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
		if(!isset($_POST['g-recaptcha-response']))
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
			'response' => sanitize_text_field($_POST['g-recaptcha-response']),
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
					$GLOBALS['dy_request_invalids'] = array(__('Invalid Recaptcha', 'dynamicpackages'));

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

if(!function_exists('money'))
{
	function money($amount)
	{
		return number_format(floatval($amount), 2, '.', ',');
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

if(!function_exists('is_valid_date'))
{
	function is_valid_date($str)
	{
		$output = false;

		if(!empty($str))
		{
			$which_var = sanitize_key($str).'_is_valid_date';
			global $$which_var;
			
			if(isset($$which_var))
			{
				$output = $$which_var;
			}
			else
			{
				if(DateTime::createFromFormat('Y-m-d', $str) !== false)
				{
					$output = true;
				}

				$GLOBALS[$which_var] = $output;
			}
		}
				
		return $output;
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

if(!function_exists('html_to_plain_text'))
{
	function html_to_plain_text($html) {
		// Array of patterns and replacements
	
		$html = strip_shortcodes($html);
	
		$search = [
			'/\[javascript protected email address\]/i',
			'/<br\s*\/?>/i',                   // <br> tags
			'/<\/?p>/i',                       // <p> and </p> tags
			'/<li>/i',                         // <li> tags
			'/<\/li>/i',                       // </li> tags
			'/<\/?ol[^>]*>/i',                 // <ol> and </ol> tags
			'/<\/?ul[^>]*>/i',                 // <ul> and </ul> tags
			'/<h[1-6][^>]*>(.*?)<\/h[1-6]>/i', // <h1> to <h6> tags
			'/\n+/'                            // multiple new lines
		];
	
		$replace = [
			"\n",                  // replace [javascript protected email address] with newline
			"\n",                  // replace <br> with newline
			"\n",                  // replace <p> and </p> with newline
			"\n* ",                // replace <li> with newline and bullet
			"",                    // replace </li> with nothing
			"\n",                  // replace <ol> and </ol> with newline
			"\n",                  // replace <ul> and </ul> with newline
			"$1: ",                // replace <h1> to <h6> with title followed by ":"
			"\n"                   // replace multiple new lines with single newline
		];
	
		// Perform replacements
		$text = preg_replace($search, $replace, $html);
	
		// Strip any remaining HTML tags
		$text = wp_strip_all_tags($text);
	
		// Trim leading/trailing whitespace and return
		return trim($text);
	}
	
}




?>