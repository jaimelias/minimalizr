<?php

if ( !defined( 'WPINC' ) ) exit;

if(!function_exists('dy_sanitize_per_line')) {

    function dy_sanitize_per_line($sanitize_func, $str, $max_items = 10) 	{
        
        if(!function_exists($sanitize_func)) {
            write_log('dy_sanitize_per_line expects a valid sanitization function as the first argument. '.$sanitize_func.' given.');
            return '';
        }

		if(!is_string($str)) {
			write_log('dy_sanitize_per_line expects a string as the second argument. '.gettype($str).' given.');
			return '';
		}

		if(!is_int($max_items) || $max_items < 1) {
			write_log('dy_sanitize_per_line expects a positive integer as the third argument. '.gettype($max_items).' given.');
			return '';
		}

		$str = html_entity_decode($str);
		$emails = explode("\r\n", $str);		
		$arr = array_slice(array_unique(array_filter(array_map($sanitize_func, $emails))), 0, 10) ;

		return implode("\r\n", $arr);
	}
}

if(!function_exists('dy_sanitize_email_per_line')) {

    function dy_sanitize_email_per_line($str) 	{
		$max_items = 10;
        return dy_sanitize_per_line('sanitize_email', $str, $max_items);
	}
}

?>