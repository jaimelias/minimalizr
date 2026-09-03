<?php

if ( !defined( 'WPINC' ) ) exit;

if(!function_exists('dy_sanitize_per_line')) {

    function dy_sanitize_per_line(Closure|string $sanitize_func, string $str, int|null $max_items = null) : string	{
        
		if (!is_callable($sanitize_func)) {
			$sanitize_func = 'sanitize_text_field';
		}

		if(!is_string($str)) {
			write_log('dy_sanitize_per_line expects a string as the second param. '.gettype($str).' given.');
			return '';
		}

		if($max_items !== null && $max_items <= 0) {
			write_log('dy_sanitize_per_line expects a positive integer or null as the third param. '.esc_html($max_items).' given.');
			return '';
		}

		$str = html_entity_decode($str);
		$emails = explode("\r\n", $str);

		if($max_items === null) {
			$arr = array_unique(array_filter(array_map($sanitize_func, $emails)));
		}
		else {
			$arr = array_slice(array_unique(array_filter(array_map($sanitize_func, $emails))), 0, $max_items) ;
		}

		return implode("\r\n", $arr);
	}
}

if(!function_exists('dy_sanitize_email_per_line')) {

    function dy_sanitize_email_per_line(string $str) : string	{
		$max_items = 10;
        return dy_sanitize_per_line('sanitize_email', $str, $max_items);
	}
}

?>