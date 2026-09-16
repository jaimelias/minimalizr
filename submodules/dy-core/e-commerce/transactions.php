<?php

if ( ! defined( 'WPINC' ) ) {
	exit;
}

class dy_tx
{

	static $tx_sign_slug = 'tx-sign';
	static array $cache = [];

	private const TRANSIENT_PREFIX = 'tx_id_';

	private const BOOKING_CONTRACT = [
		'pax_regular',
		'pax_discount',
		'pax_free',
		'transport_type',
		'route',
		'start_date',
		'start_hour',
		'end_date',
		'end_hour',
		'additional_time',
		'coupon_code',
		'force_availability',
	];

	private const CONTACT_CONTRACT = [
		'first_name',
		'lastname',
		'phone',
		'country_calling_code',
		'email',
		'repeat_email',
		'inquiry',
	];

	/**
	 * Allowed booking and contact properties sanitized for requests and transaction storage.
	 * pax_regular: Number of regular-rate participants used to calculate occupancy and booking prices.
	 * pax_discount: Number of discounted-rate participants used to calculate occupancy and booking prices.
	 * pax_free: Number of participants admitted free of charge, included in the booking party count.
	 * transport_type: Transport trip selection, where 0 means one-way and 1 means round trip.
	 * route: Transport direction, where 0 follows the configured origin-to-destination route and 1 reverses it.
	 * start_date: Selected booking start, check-in, or departure date used for scheduling and pricing.
	 * start_hour: Selected start or departure time used for bookings with an hourly schedule.
	 * end_date: Selected booking end, check-out, or transport return date used for scheduling and duration.
	 * end_hour: Selected end or return time used for bookings with an hourly schedule.
	 * additional_time: Selected total duration in the package's units, used to extend its base duration within configured limits.
	 * coupon_code: Submitted promotional code used to validate and apply a booking discount.
	 * force_availability: Boolean flag carrying the force-availability selection through the booking flow.
	 * first_name: Customer's given name used to identify the booking contact and personalize communications.
	 * lastname: Customer's surname used with the given name to identify the booking contact.
	 * phone: Customer's telephone number used to contact them about the booking.
	 * country_calling_code: International dialing prefix associated with the customer's telephone number.
	 * email: Customer's email address used for notifications and as part of the signed transaction identity.
	 * repeat_email: Re-entered email address checked against email to prevent contact-address mistakes.
	 * inquiry: Customer's free-text message or booking inquiry, sanitized while preserving line breaks.
	 */
	private const PAYLOAD_CONTRACT = [
		...self::BOOKING_CONTRACT,
		...self::CONTACT_CONTRACT,
	];


	/**
	 * Create the initial transaction record.
	 */
	public static function create(string $tx_id, array $arr = []): bool
	{
		$tx_id = trim($tx_id);

		if ($tx_id === '') {
			return false;
		}

		$identity = self::identity_values($tx_id, $arr);

		if (
			$identity['email'] === ''
			|| ! is_email($identity['email'])
			|| $identity['dy_request'] === ''
			|| $identity['dy_id'] <= 0
		) {
			return false;
		}

		$tx = [
			'tx_id'    => $tx_id,
			'secret_tx_id'    => self::sign_secret([
				$tx_id,
				$identity['email'],
				$identity['dy_request'],
				$identity['dy_id'],
			]),
			'dy_request'      => $identity['dy_request'],
			'email'           => $identity['email'],
			'dy_id'           => $identity['dy_id'],
			'status'          => 'started',
		];

		return (bool) set_transient(
			self::transient_key($tx_id),
			$tx,
			HOUR_IN_SECONDS
		);
	}

	/**
	 * Sign the transaction identity using the same concatenation as the REST endpoint.
	 */
	public static function sign_secret(array $arr = []): string
	{
		$values = self::signing_values($arr);

		return hash_hmac(
			'sha256',
			implode('', $values),
			wp_salt('auth')
		);
	}

	/**
	 * Validate both the transaction signature and its identity fields.
	 */
	public static function validate(string $tx_id, array $expected_arr = []): bool
	{
		$tx_id = trim($tx_id);
		$tx = self::get_stored_tx($tx_id);

		if ($tx === null) {
			return false;
		}

		if ($expected_arr === []) {
			$expected_arr = [
				$tx_id,
				(string) self::request_value('email'),
				(string) secure_post('dy_request', '', 'sanitize_key'),
				(int) secure_post('dy_id', 0, 'absint'),
			];
		}

		if (array_key_exists('tx_id', $expected_arr)) {
			$expected_unique_tx_id = $expected_arr['tx_id'];
			if (! is_scalar($expected_unique_tx_id) || (string) $expected_unique_tx_id !== $tx_id) {
				return false;
			}
		} elseif (count($expected_arr) >= 4) {
			$expected_values = array_values($expected_arr);
			if (! is_scalar($expected_values[0]) || (string) $expected_values[0] !== $tx_id) {
				return false;
			}
		}

		$expected = self::identity_values($tx_id, $expected_arr);
		$stored = [
			'tx_id'       => (string) ($tx['tx_id'] ?? ''),
			'email'       => (string) ($tx['email'] ?? ''),
			'dy_request'  => (string) ($tx['dy_request'] ?? ''),
			'dy_id'       => (int) ($tx['dy_id'] ?? 0),
		];
		$expected_values = [
			$expected['tx_id'],
			$expected['email'],
			$expected['dy_request'],
			$expected['dy_id'],
		];
		$stored_values = [
			$stored['tx_id'],
			$stored['email'],
			$stored['dy_request'],
			$stored['dy_id'],
		];
		$stored_secret = (string) ($tx['secret_tx_id'] ?? '');

		if ($stored_secret === '' || $expected_values !== $stored_values) {
			return false;
		}

		return hash_equals($stored_secret, self::sign_secret($expected_values));
	}

	/**
	 * Retrieve a transaction record from its transient.
	 */
	public static function get_stored_tx(string $tx_id): ?array
	{
		$tx_id = trim($tx_id);

		if ($tx_id === '') {
			return null;
		}

		$stored = get_transient(self::transient_key($tx_id));

		if (! is_array($stored) && ! is_object($stored)) {
			return null;
		}

		$tx = self::normalize_transaction($stored);

		if ((string) ($tx['tx_id'] ?? '') !== $tx_id) {
			return null;
		}

		return $tx;
	}

	/**
	 * Update a transaction array, or use the legacy status/payload arguments.
	 */
	public static function update(
		string|array $tx_id = '',
		string $new_status = '',
		array $payload = [],
		int $expiration_in_seconds = 0
	): bool {
		$submitted = is_array($tx_id) ? $tx_id : null;
		$tx_id = $submitted !== null ? (string) ($submitted['tx_id'] ?? '') : $tx_id;
		$tx = self::get_stored_tx($tx_id);

		if ($tx === null) {
			return false;
		}

		if ($submitted !== null) {
			if (!self::validate($tx_id, $submitted)) {
				return false;
			}
			$new_status = (string) ($submitted['status'] ?? '');
			$payload = $submitted;
		}

		$current_status = (string) ($tx['status'] ?? '');
		$new_status = $new_status !== '' ? sanitize_key($new_status) : $current_status;
		$request_type = (string) ($tx['dy_request'] ?? '');
		$allowed_statuses = $request_type === 'paguelo_facil_on'
			? ['started', 'processing', 'success', 'declined', 'error']
			: ['started', 'processing', 'success'];

		if (! in_array($new_status, $allowed_statuses, true)) {
			return false;
		}

		$tx['status'] = $new_status;

		if (($new_status === 'success' || $submitted !== null) && $payload !== []) {
			$tx = [...$tx, ...self::sanitize_payload($payload)];
		}

		if ($submitted !== null && isset($submitted['confirmation'])) {
			$result = is_array($submitted['confirmation']) ? $submitted['confirmation'] : [];
			$tx['confirmation'] = [
				'title' => sanitize_text_field((string) ($result['title'] ?? '')),
				'content' => wp_kses_post((string) ($result['content'] ?? '')),
				'excerpt' => sanitize_text_field((string) ($result['excerpt'] ?? '')),
				'events' => self::sanitize_conversion_events($result['events'] ?? [], $tx_id),
			];
		}

		$expiration = $expiration_in_seconds > 0
			? $expiration_in_seconds
			: ($new_status === 'success' ? DAY_IN_SECONDS : HOUR_IN_SECONDS);

		return (bool) set_transient(
			self::transient_key($tx_id),
			$tx,
			$expiration
		);
	}

	/** Keep only the conversion fields produced by dy_gtag_queue_server_event(). */
	private static function sanitize_conversion_events(mixed $events, string $tx_id): array
	{
		$output = [];
		foreach (is_array($events) ? $events : [] as $event) {
			$name = $event['name'] ?? '';
			$params = $event['params'] ?? [];
			if (!in_array($name, ['purchase', 'generate_lead'], true)
				|| !is_array($params) || ($params['transaction_id'] ?? '') !== $tx_id) {
				continue;
			}
			$clean = [
				'transaction_id' => $tx_id,
				'value' => max(0, (float) ($params['value'] ?? 0)),
				'currency' => sanitize_text_field((string) ($params['currency'] ?? '')),
			];
			if ($name === 'purchase') {
				$clean['items'] = [];
				foreach ((array) ($params['items'] ?? []) as $item) {
					if (!is_array($item)) continue;
					$clean['items'][] = [
						'item_id' => sanitize_text_field((string) ($item['item_id'] ?? '')),
						'item_name' => sanitize_text_field((string) ($item['item_name'] ?? '')),
						'price' => max(0, (float) ($item['price'] ?? 0)),
						'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
					];
				}
			}
			$output[] = ['name' => $name, 'params' => $clean];
		}
		return $output;
	}

	private static function transient_key(string $tx_id): string
	{
		return self::TRANSIENT_PREFIX . $tx_id;
	}

	/**
	 * Normalize either a positional identity array or named identity fields.
	 *
	 * @return array{tx_id: string, email: string, dy_request: string, dy_id: int}
	 */
	private static function identity_values(string $tx_id, array $arr): array
	{
		if (
			array_key_exists('email', $arr)
			|| array_key_exists('dy_request', $arr)
			|| array_key_exists('dy_id', $arr)
		) {
			$email = $arr['email'] ?? '';
			$dy_request = $arr['dy_request'] ?? '';
			$dy_id = $arr['dy_id'] ?? 0;

			return [
				'tx_id' => $tx_id,
				'email'       => is_scalar($email) ? sanitize_email((string) $email) : '',
				'dy_request'  => is_scalar($dy_request) ? sanitize_key((string) $dy_request) : '',
				'dy_id'       => is_scalar($dy_id) ? absint($dy_id) : 0,
			];
		}

		$values = array_values($arr);
		$starts_with_id = isset($values[0]) && (string) $values[0] === $tx_id;
		$offset = $starts_with_id ? 1 : 0;
		$email = $values[$offset] ?? '';
		$dy_request = $values[$offset + 1] ?? '';
		$dy_id = $values[$offset + 2] ?? 0;

		return [
			'tx_id' => $tx_id,
			'email'       => is_scalar($email) ? sanitize_email((string) $email) : '',
			'dy_request'  => is_scalar($dy_request) ? sanitize_key((string) $dy_request) : '',
			'dy_id'       => is_scalar($dy_id) ? absint($dy_id) : 0,
		];
	}

	/**
	 * Return identity values in the canonical signing order.
	 *
	 * @return array<int, string>
	 */
	private static function signing_values(array $arr): array
	{
		if (
			array_key_exists('tx_id', $arr)
			|| array_key_exists('email', $arr)
			|| array_key_exists('dy_request', $arr)
			|| array_key_exists('dy_id', $arr)
		) {
			$arr = [
				$arr['tx_id'] ?? '',
				$arr['email'] ?? '',
				$arr['dy_request'] ?? '',
				$arr['dy_id'] ?? '',
			];
		}

		return array_map(
			static fn(mixed $value): string => is_scalar($value) ? (string) $value : '',
			array_values($arr)
		);
	}

	/** Keep only the request fields that may be persisted. */
	private static function sanitize_payload(array $payload): array
	{
		$payload = self::normalize_transaction($payload);
		$output = [];

		foreach (self::PAYLOAD_CONTRACT as $field) {
			$value = $payload[$field] ?? null;

			if ($value !== null && is_scalar($value)) {
				$output[$field] = self::sanitize_payload_value($field, $value);
			}
		}

		return $output;
	}

	private static function sanitize_payload_value(string $field, mixed $value): string|int|bool
	{
		if ($field === 'force_availability') {
			return (bool) filter_var($value, FILTER_VALIDATE_BOOLEAN);
		}

		if (in_array($field, ['pax_regular', 'pax_discount', 'pax_free', 'additional_time'], true)) {
			return absint($value);
		}

		if (in_array($field, ['email', 'repeat_email'], true)) {
			return sanitize_email((string) $value);
		}

		if ($field === 'inquiry') {
			return sanitize_textarea_field((string) $value);
		}

		return sanitize_text_field((string) $value);
	}

	/** Normalize current arrays and unexpired transactions stored by older versions. */
	private static function normalize_transaction(mixed $value): array
	{
		$transaction = is_object($value)
			? get_object_vars($value)
			: (is_array($value) ? $value : []);

		foreach (['booking_details', 'contact_details'] as $section) {
			$details = $transaction[$section] ?? [];
			$details = is_object($details) ? get_object_vars($details) : $details;

			if (is_array($details)) {
				$transaction = [...$transaction, ...$details];
			}

			unset($transaction[$section]);
		}

		if (is_object($transaction['confirmation'] ?? null)) {
			$transaction['confirmation'] = get_object_vars($transaction['confirmation']);
		}

		return $transaction;
	}

	/**
	 * Build the flat transaction payload from the current GET or POST request.
	 *
	 * @return array<string, string|int|bool>
	 */


	public static function get_sanitized_request_payload(): array
	{
		$cache_key = 'get_sanitized_request_payload';

		if(array_key_exists($cache_key, self::$cache)) {
			return self::$cache[$cache_key];
		}


		$fn = match (secure_server('REQUEST_METHOD')) {
			'POST' => 'secure_post',
			'GET'  => 'secure_get',
			default => null,
		};

		if ($fn === null) {
			return [];
		}

		$getter = static function (
			string $key,
			string|int|float|bool|null $default = '',
			callable|string $sanitizer = 'sanitize_text_field'
		) use ($fn): string|int|float|bool|null {
			return $fn($key, $default, $sanitizer);
		};


		$request_payload = [];

		foreach (self::PAYLOAD_CONTRACT as $field) {
			$default = in_array($field, ['pax_regular', 'pax_discount', 'pax_free', 'additional_time'], true)
				? 0
				: ($field === 'force_availability' ? false : '');
			$sanitizer = match (true) {
				in_array($field, ['pax_regular', 'pax_discount', 'pax_free', 'additional_time'], true) => 'absint',
				in_array($field, ['email', 'repeat_email'], true) => 'sanitize_email',
				$field === 'inquiry' => 'sanitize_textarea_field',
				default => 'sanitize_text_field',
			};
			$request_payload[$field] = self::sanitize_payload_value(
				$field,
				$getter($field, $default, $sanitizer)
			);
		}

		return self::$cache[$cache_key] = $request_payload;
	}

	/**
	 * Return a sanitized POST|GET payload value by its field name.
	 */
	public static function request_value(string $key): string|int|bool|null
	{

		$flat_payload = self::get_sanitized_request_payload();

		if(!array_key_exists($key, $flat_payload)) {

			$message = sprintf(
				'dy_tx::request_value(): unknown sanitized payload key "%s".',
				$key
			);

			write_log($message, true, true, 'payload_contract_error');

			return null;
		}

		return $flat_payload[$key] ?? null;
	}

	public static function all_dy_request_types(): array
	{
		$request_types = apply_filters(
			'all_dy_request_types',
			['contact']
		);

		if (!is_array($request_types)) {
			return ['contact'];
		}

		$request_types = array_filter(
			$request_types,
			static fn(mixed $request_type): bool =>
				is_string($request_type) && $request_type !== ''
		);

		$request_types = array_map(
			'sanitize_key',
			$request_types
		);

		return array_values(array_unique($request_types));
	}

}
