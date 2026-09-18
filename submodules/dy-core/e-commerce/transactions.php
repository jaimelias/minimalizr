<?php

if ( ! defined( 'WPINC' ) ) {
	exit;
}

#[AllowDynamicProperties]
class dy_tx
{

	static array $cache = [];
	public static ?object $transaction_obj = null;

	private const TRANSIENT_PREFIX = 'tx_id_';

	private const PAYLOAD_CONTRACT = [
		'booking_details' => [
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
		],
		'contact_details' => [
			'first_name',
			'lastname',
			'phone',
			'country_calling_code',
			'email',
			'repeat_email',
			'inquiry',
		]
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

		$tx = (object) [
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
			'booking_details' => (object) [],
			'contact_details' => (object) [],
		];

		self::$transaction_obj = $tx;

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
			'tx_id' => (string) ($tx->tx_id ?? ''),
			'email'       => (string) ($tx->email ?? ''),
			'dy_request'  => (string) ($tx->dy_request ?? ''),
			'dy_id'       => (int) ($tx->dy_id ?? 0),
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
		$stored_secret = (string) ($tx->secret_tx_id ?? '');

		if ($stored_secret === '' || $expected_values !== $stored_values) {
			return false;
		}

		return hash_equals($stored_secret, self::sign_secret($expected_values));
	}

	/**
	 * Retrieve a transaction record from its transient.
	 */
	public static function get_stored_tx(string $tx_id): ?object
	{
		$tx_id = trim($tx_id);

		if ($tx_id === '') {
			return null;
		}

		$stored = get_transient(self::transient_key($tx_id));

		if (! is_array($stored) && ! is_object($stored)) {
			return null;
		}

		$tx = is_object($stored) ? $stored : (object) $stored;

		if ((string) ($tx->tx_id ?? '') !== $tx_id) {
			return null;
		}

		self::$transaction_obj = $tx;

		return $tx;
	}

	/**
	 * Update a transaction result object, or use the legacy status/payload arguments.
	 */
	public static function update(
		string|object $tx_id = '',
		string $new_status = '',
		array $payload = [],
		int $expiration_in_seconds = 0
	): bool {
		$submitted = is_object($tx_id) ? $tx_id : null;
		$tx_id = $submitted !== null ? (string) ($submitted->tx_id ?? '') : $tx_id;
		$tx = self::get_stored_tx($tx_id);

		if ($tx === null) {
			return false;
		}

		if ($submitted !== null) {
			// The object API updates results, never the signed transaction identity.
			if (!self::validate($tx_id, get_object_vars($submitted))) {
				return false;
			}
			$new_status = (string) ($submitted->status ?? '');
			$payload = get_object_vars($submitted);
		}

		$current_status = (string) ($tx->status ?? '');
		$new_status = $new_status !== '' ? sanitize_key($new_status) : $current_status;
		$request_type = (string) ($tx->dy_request ?? '');
		$allowed_statuses = $request_type === 'paguelo_facil_on'
			? ['started', 'processing', 'success', 'declined', 'error']
			: ['started', 'processing', 'success'];

		if (! in_array($new_status, $allowed_statuses, true)) {
			return false;
		}

		$tx->status = $new_status;

		if (($new_status === 'success' || $submitted !== null) && $payload !== []) {
			$sanitized_payload = self::sanitize_payload($payload);
			$tx->booking_details = (object) array_merge(
				self::object_to_array($tx->booking_details ?? null),
				$sanitized_payload['booking_details']
			);
			$tx->contact_details = (object) array_merge(
				self::object_to_array($tx->contact_details ?? null),
				$sanitized_payload['contact_details']
			);
		}

		if ($submitted !== null && isset($submitted->confirmation)) {
			$result = self::object_to_array($submitted->confirmation);
			$tx->confirmation = [
				'title' => sanitize_text_field((string) ($result['title'] ?? '')),
				'content' => wp_kses_post((string) ($result['content'] ?? '')),
				'excerpt' => sanitize_text_field((string) ($result['excerpt'] ?? '')),
				'events' => self::sanitize_conversion_events($result['events'] ?? [], $tx_id),
			];
		}

		$expiration = $expiration_in_seconds > 0
			? $expiration_in_seconds
			: ($new_status === 'success' ? DAY_IN_SECONDS : HOUR_IN_SECONDS);

		// Keep the in-memory record in sync before persisting it.
		self::$transaction_obj = $tx;

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
				'email'       => is_scalar($email) ? dy_sanitize_email((string) $email) : '',
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
			'email'       => is_scalar($email) ? dy_sanitize_email((string) $email) : '',
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

	/**
	 * Keep only the fields that may be persisted in a successful transaction.
	 *
	 * @return array{booking_details: array<string, mixed>, contact_details: array<string, mixed>}
	 */
	private static function sanitize_payload(array $payload): array
	{
		$output = [
			'booking_details' => [],
			'contact_details' => [],
		];

		foreach (self::PAYLOAD_CONTRACT as $section => $fields) {
			$section_payload = $payload[$section] ?? [];
			$section_payload = is_object($section_payload)
				? get_object_vars($section_payload)
				: (is_array($section_payload) ? $section_payload : []);

			foreach ($fields as $field) {
				$value = array_key_exists($field, $section_payload)
					? $section_payload[$field]
					: ($payload[$field] ?? null);

				if ($value === null || ! is_scalar($value)) {
					continue;
				}

				$output[$section][$field] = self::sanitize_payload_value($field, $value);
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
			return dy_sanitize_email((string) $value);
		}

		if ($field === 'inquiry') {
			return sanitize_textarea_field((string) $value);
		}

		return sanitize_text_field((string) $value);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function object_to_array(mixed $value): array
	{
		if (is_object($value)) {
			return get_object_vars($value);
		}

		return is_array($value) ? $value : [];
	}

	/**
	 * Validates that the payload keys exactly match the contract.
	 * Order doesn't matter; keys are compared via symmetric difference.
	 */
	private static function validate_payload_contract(array $payload, array $contract): void {
		foreach ($contract as $section => $expected_keys) {
			if (!array_key_exists($section, $payload)) {
				$message = sprintf(
					'dy_tx payload contract mismatch: required section "%s" is missing.',
					$section
				);
				write_log($message, true, true, 'payload_contract_error');

				wp_die(
					'The booking request could not be processed because of a server configuration error.',
					'Transaction Payload Configuration Error',
					['response' => 500]
				);
			}

			$actual_keys = array_keys($payload[$section]);
			$missing     = array_diff($expected_keys, $actual_keys);
			$extra       = array_diff($actual_keys, $expected_keys);

			if ($missing !== [] || $extra !== []) {

				$message = sprintf(
					'dy_tx payload contract mismatch in "%s": missing keys [%s]; unexpected keys [%s].',
					$section,
					implode(', ', $missing),
					implode(', ', $extra)
				);

				write_log($message, true, true, 'payload_contract_error');

				wp_die(
					'The booking request could not be processed because of a server configuration error.',
					'Transaction Payload Configuration Error',
					['response' => 500]
				);
			}
		}
	}

	/**
	 * Build the transaction payload from the current GET or POST request
	 * using the fields defined in PAYLOAD_CONTRACT.
	 *
	 * @return array{booking_details: array<string, mixed>, contact_details: array<string, mixed>}
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


		$request_payload = [
			'booking_details' => [
				'pax_regular'       => $getter('pax_regular', 0, 'absint'),
				'pax_discount'      => $getter('pax_discount', 0, 'absint'),
				'pax_free'          => $getter('pax_free', 0, 'absint'),
				'transport_type'    => $getter('transport_type'),
				'route'             => $getter('route'),
				'start_date'        => $getter('start_date'),
				'start_hour'        => $getter('start_hour'),
				'end_date'          => $getter('end_date'),
				'end_hour'          => $getter('end_hour'),
				'additional_time'   => $getter('additional_time', 0, 'absint'),
				'coupon_code'       => $getter('coupon_code'),
				'force_availability'=> (bool) filter_var(
					$getter('force_availability', false),
					FILTER_VALIDATE_BOOLEAN
				),
			],
			'contact_details' => [
				'first_name'          => $getter('first_name'),
				'lastname'            => $getter('lastname'),
				'phone'               => $getter('phone'),
				'country_calling_code'=> $getter('country_calling_code'),
				'email'               => $getter('email', '', 'sanitize_email'),
				'repeat_email'        => $getter('repeat_email', '', 'sanitize_email'),
				'inquiry'             => $getter('inquiry', '', 'sanitize_textarea_field'),
			],
		];

		self::validate_payload_contract($request_payload, self::PAYLOAD_CONTRACT);

		return self::$cache[$cache_key] = $request_payload;
	}

	public static function flat_sanitized_request_payload(): array
	{
		$cache_key = 'sanitized_request_payload_flat';

		if (array_key_exists($cache_key, self::$cache)) {
			return self::$cache[$cache_key];
		}

		return self::$cache[$cache_key] = array_merge(
			...array_values(self::get_sanitized_request_payload())
		);
	}

	/**
	 * Return a sanitized POST|GET payload value by its field name.
	 */
	public static function request_value(string $key): string|int|bool|null
	{

		$flat_payload = self::flat_sanitized_request_payload();

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

}
