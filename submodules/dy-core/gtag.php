<?php

if ( !defined( 'WPINC' ) ) exit;


if(!function_exists('dy_gtag_is_valid_transaction_id'))
{
	function dy_gtag_is_valid_transaction_id($transaction_id, $signature)
	{
		$transaction_id = trim((string) $transaction_id);
		$signature = trim((string) $signature);

		if(
			1 !== preg_match(
				'/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
				$transaction_id
			)
			|| 1 !== preg_match('/^[0-9a-f]{64}$/i', $signature)
		)
		{
			return false;
		}

		$expected_signature = hash_hmac(
			'sha256',
			$transaction_id,
			wp_salt('nonce')
		);

		return hash_equals($expected_signature, $signature);
	}
}

if(!function_exists('dy_gtag_build_item'))
{
	function dy_gtag_build_item($post_id, $item_name, $quantity, $value)
	{
		$post_id = absint($post_id);
		$quantity = max(1, absint($quantity));
		$value = max(0, (float) $value);

		if(empty($item_name) && $post_id > 0)
		{
			$item_name = get_the_title($post_id);
		}

		$item_name = html_entity_decode(
			wp_strip_all_tags((string) $item_name),
			ENT_QUOTES,
			get_bloginfo('charset') ?: 'UTF-8'
		);

		return array(
			'item_id' => 'post_' . $post_id,
			'item_name' => sanitize_text_field($item_name),
			'price' => round($value / $quantity, 6),
			'quantity' => $quantity
		);
	}
}

if(!function_exists('dy_gtag_queue_server_event'))
{
	function dy_gtag_queue_server_event(
		$event_name,
		$transaction_id,
		$value,
		$currency,
		$items = array()
	)
	{
		$allowed_events = array('purchase', 'generate_lead');
		$event_name = sanitize_key($event_name);
		$transaction_id = trim((string) $transaction_id);
		$currency = strtoupper(trim((string) $currency));

		if(
			!in_array($event_name, $allowed_events, true)
			|| 1 !== preg_match('/^[A-Za-z0-9_-]{1,64}$/', $transaction_id)
			|| 1 !== preg_match('/^[A-Z]{3}$/', $currency)
		)
		{
			return false;
		}

		$params = array(
			'transaction_id' => $transaction_id,
			'value' => round(max(0, (float) $value), 2),
			'currency' => $currency
		);

		if($event_name === 'purchase')
		{
			if(empty($items))
			{
				return false;
			}

			$params['items'] = array_values($items);
		}

		if(
			!isset($GLOBALS['dy_gtag_server_events'])
			|| !is_array($GLOBALS['dy_gtag_server_events'])
		)
		{
			$GLOBALS['dy_gtag_server_events'] = array();
		}

		/*
		 * Evita registrar dos veces el mismo evento durante
		 * una única ejecución PHP.
		 */
		$event_key = $event_name . '|' . $transaction_id;

		$GLOBALS['dy_gtag_server_events'][$event_key] = array(
			'name' => $event_name,
			'params' => $params
		);

		return true;
	}
}

?>