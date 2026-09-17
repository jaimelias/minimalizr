<?php

if (!defined('WPINC')) {
	exit;
}

class dy_tx
{
	public static string $tx_sign_slug = 'tx-sign';
	public static array $cache = [];

	private const SCHEMA_VERSION = 2;
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

	private const PAYLOAD_CONTRACT = [
		...self::BOOKING_CONTRACT,
		...self::CONTACT_CONTRACT,
	];

	private const SERVICE_SCALAR_CONTRACT = [
		'dy_id',
		'dy_request',
		'title',
		'url',
		'description',
		'location',
		'confirmation_message',
		'timezone',
		'calendar_start',
		'total',
		'payment_amount',
		'formatted_payment_amount',
		'currency',
		'payment_type',
	];

	private const GATEWAY_METADATA_CONTRACT = [
		'payment_url',
		'payment_amount',
		'formatted_payment_amount',
		'service_fee',
		'payment_type',
		'domain',
		'username',
		'destination',
		'qrcode_url',
		'qrcode',
		'number',
		'business',
		'network_id',
		'network',
		'network_name',
		'network_address',
		'asset_name',
		'address',
		'debug_mode',
		'environment',
		'currency',
	];

	private static ?array $current_transaction = null;

	/** Create the signed transaction shell used by the submission engine. */
	public static function create(string $tx_id, array $arr = []): bool
	{
		$tx_id = trim($tx_id);
		if ($tx_id === '') {
			return false;
		}

		$identity = self::identity_values($tx_id, $arr);
		if (
			$identity['email'] === ''
			|| !is_email($identity['email'])
			|| $identity['dy_request'] === ''
			|| $identity['dy_id'] <= 0
		) {
			return false;
		}

		$now = gmdate('c');
		$tx = [
			'schema_version' => self::SCHEMA_VERSION,
			'status' => 'started',
			'tx_id' => $tx_id,
			'secret_tx_id' => self::sign_secret([
				$tx_id,
				$identity['email'],
				$identity['dy_request'],
				$identity['dy_id'],
			]),
			'payload_contract' => self::sanitize_payload($arr),
			'service_contract' => [
				'dy_id' => $identity['dy_id'],
				'dy_request' => $identity['dy_request'],
			],
			'gateway' => [],
			'events' => [],
			'timestamps' => [
				'created_at' => $now,
				'updated_at' => $now,
			],
		];
		$tx['payload_contract']['email'] = $identity['email'];

		return (bool) set_transient(
			self::transient_key($tx_id),
			$tx,
			HOUR_IN_SECONDS
		);
	}

	/** Sign the canonical transaction identity. */
	public static function sign_secret(array $arr = []): string
	{
		return hash_hmac(
			'sha256',
			implode('', self::signing_values($arr)),
			wp_salt('auth')
		);
	}

	/** Validate the signature and immutable identity fields. */
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
			$expected_id = $expected_arr['tx_id'];
			if (!is_scalar($expected_id) || (string) $expected_id !== $tx_id) {
				return false;
			}
		} elseif (count($expected_arr) >= 4) {
			$values = array_values($expected_arr);
			if (!is_scalar($values[0]) || (string) $values[0] !== $tx_id) {
				return false;
			}
		}

		$expected = self::identity_values($tx_id, $expected_arr);
		$stored = self::identity_values($tx_id, $tx);
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

		return $stored_secret !== ''
			&& $expected_values === $stored_values
			&& hash_equals($stored_secret, self::sign_secret($stored_values));
	}

	/** Retrieve and normalize a transaction transient. */
	public static function get_stored_tx(string $tx_id): ?array
	{
		$tx_id = trim($tx_id);
		if ($tx_id === '') {
			return null;
		}

		$stored = get_transient(self::transient_key($tx_id));
		if (!is_array($stored) && !is_object($stored)) {
			return null;
		}

		$tx = self::normalize_transaction($stored);
		return (string) ($tx['tx_id'] ?? '') === $tx_id ? $tx : null;
	}

	/**
	 * Persist a transaction array, or support the legacy update(id, status, payload) call.
	 * Only explicit contract fields reach the transient.
	 */
	public static function update(
		string|array $tx_id = '',
		string $new_status = '',
		array $payload = [],
		int $expiration_in_seconds = 0
	): bool {
		$submitted = is_array($tx_id) ? self::normalize_input_array($tx_id) : null;
		$tx_id = $submitted !== null ? (string) ($submitted['tx_id'] ?? '') : trim($tx_id);
		$stored = self::get_stored_tx($tx_id);
		if ($stored === null) {
			return false;
		}

		if ($submitted !== null) {
			if (!self::validate($tx_id, $submitted)) {
				return false;
			}
			$new_status = (string) ($submitted['status'] ?? '');
		}

		$current_status = sanitize_key((string) ($stored['status'] ?? 'started'));
		$new_status = $new_status !== '' ? sanitize_key($new_status) : $current_status;
		$request_type = (string) self::service_value('dy_request', '', $stored);
		$allowed_statuses = $request_type === 'paguelo_facil_on'
			? ['started', 'processing', 'success', 'declined', 'error']
			: ['started', 'processing', 'success', 'error'];

		if (!in_array($new_status, $allowed_statuses, true)) {
			return false;
		}
		$transitions = [
			'started' => $allowed_statuses,
			'processing' => array_values(array_intersect(
				$allowed_statuses,
				['processing', 'success', 'declined', 'error']
			)),
			'success' => ['success'],
			'declined' => ['declined'],
			'error' => ['error'],
		];
		if (!in_array($new_status, $transitions[$current_status] ?? [], true)) {
			return false;
		}

		$stored_payload = self::payload($stored);
		$stored_service = self::service($stored);
		$incoming_payload = $submitted !== null
			? self::sanitize_payload($submitted['payload_contract'] ?? $submitted)
			: self::sanitize_payload($payload);
		$incoming_service = $submitted !== null
			? self::sanitize_service((array) ($submitted['service_contract'] ?? []))
			: [];

		$payload_contract = [...$stored_payload, ...$incoming_payload];
		$service_contract = [...$stored_service, ...$incoming_service];
		$stored_identity = self::identity_values($tx_id, $stored);
		$payload_contract['email'] = $stored_identity['email'];
		$service_contract['dy_id'] = $stored_identity['dy_id'];
		$service_contract['dy_request'] = $stored_identity['dy_request'];

		$gateway = $submitted !== null && array_key_exists('gateway', $submitted)
			? self::sanitize_gateway($submitted['gateway'])
			: self::gateway($stored);
		$events = $submitted !== null && array_key_exists('events', $submitted)
			? self::sanitize_conversion_events($submitted['events'], $tx_id)
			: self::events($stored);
		$created_at = (string) (($stored['timestamps']['created_at'] ?? '') ?: gmdate('c'));

		$tx = [
			'schema_version' => self::SCHEMA_VERSION,
			'status' => $new_status,
			'tx_id' => $tx_id,
			'secret_tx_id' => (string) ($stored['secret_tx_id'] ?? ''),
			'payload_contract' => $payload_contract,
			'service_contract' => $service_contract,
			'gateway' => $gateway,
			'events' => $events,
			'timestamps' => [
				'created_at' => sanitize_text_field($created_at),
				'updated_at' => gmdate('c'),
			],
		];

		$expiration = $expiration_in_seconds > 0
			? $expiration_in_seconds
			: ($new_status === 'success' ? DAY_IN_SECONDS : HOUR_IN_SECONDS);

		return (bool) set_transient(self::transient_key($tx_id), $tx, $expiration);
	}

	/** Set the structured transaction used while rendering a confirmation GET. */
	public static function set_current_transaction(?array $tx): void
	{
		self::$current_transaction = $tx === null ? null : self::normalize_transaction($tx);
		unset(self::$cache['get_sanitized_request_payload']);
	}

	public static function current_transaction(): ?array
	{
		return self::$current_transaction;
	}

	public static function payload(?array $tx = null): array
	{
		$tx ??= self::$current_transaction;
		return is_array($tx['payload_contract'] ?? null) ? $tx['payload_contract'] : [];
	}

	public static function payload_value(string $key, mixed $default = null, ?array $tx = null): mixed
	{
		$payload = self::payload($tx);
		return array_key_exists($key, $payload) ? $payload[$key] : $default;
	}

	public static function has_payload_value(string $key, ?array $tx = null): bool
	{
		return array_key_exists($key, self::payload($tx));
	}

	public static function service(?array $tx = null): array
	{
		$tx ??= self::$current_transaction;
		return is_array($tx['service_contract'] ?? null) ? $tx['service_contract'] : [];
	}

	public static function service_value(string $key, mixed $default = null, ?array $tx = null): mixed
	{
		$service = self::service($tx);
		return array_key_exists($key, $service) ? $service[$key] : $default;
	}

	public static function gateway(?array $tx = null): array
	{
		$tx ??= self::$current_transaction;
		return is_array($tx['gateway'] ?? null) ? $tx['gateway'] : [];
	}

	public static function events(?array $tx = null): array
	{
		$tx ??= self::$current_transaction;
		return is_array($tx['events'] ?? null) ? $tx['events'] : [];
	}

	private static function transient_key(string $tx_id): string
	{
		return self::TRANSIENT_PREFIX . $tx_id;
	}

	/** @return array{tx_id:string,email:string,dy_request:string,dy_id:int} */
	private static function identity_values(string $tx_id, array $arr): array
	{
		$payload = is_array($arr['payload_contract'] ?? null) ? $arr['payload_contract'] : [];
		$service = is_array($arr['service_contract'] ?? null) ? $arr['service_contract'] : [];
		if (
			$payload !== []
			|| $service !== []
			|| array_key_exists('email', $arr)
			|| array_key_exists('dy_request', $arr)
			|| array_key_exists('dy_id', $arr)
		) {
			$email = $payload['email'] ?? $arr['email'] ?? '';
			$dy_request = $service['dy_request'] ?? $arr['dy_request'] ?? '';
			$dy_id = $service['dy_id'] ?? $arr['dy_id'] ?? 0;
			return [
				'tx_id' => $tx_id,
				'email' => is_scalar($email) ? dy_sanitize_email((string) $email) : '',
				'dy_request' => is_scalar($dy_request) ? sanitize_key((string) $dy_request) : '',
				'dy_id' => is_scalar($dy_id) ? absint($dy_id) : 0,
			];
		}

		$values = array_values($arr);
		$offset = isset($values[0]) && (string) $values[0] === $tx_id ? 1 : 0;
		$email = $values[$offset] ?? '';
		$dy_request = $values[$offset + 1] ?? '';
		$dy_id = $values[$offset + 2] ?? 0;
		return [
			'tx_id' => $tx_id,
			'email' => is_scalar($email) ? dy_sanitize_email((string) $email) : '',
			'dy_request' => is_scalar($dy_request) ? sanitize_key((string) $dy_request) : '',
			'dy_id' => is_scalar($dy_id) ? absint($dy_id) : 0,
		];
	}

	/** @return array<int,string> */
	private static function signing_values(array $arr): array
	{
		if (
			array_key_exists('tx_id', $arr)
			|| array_key_exists('payload_contract', $arr)
			|| array_key_exists('service_contract', $arr)
			|| array_key_exists('email', $arr)
			|| array_key_exists('dy_request', $arr)
			|| array_key_exists('dy_id', $arr)
		) {
			$tx_id = is_scalar($arr['tx_id'] ?? '') ? (string) ($arr['tx_id'] ?? '') : '';
			$identity = self::identity_values($tx_id, $arr);
			$arr = [$tx_id, $identity['email'], $identity['dy_request'], $identity['dy_id']];
		}

		return array_map(
			static fn(mixed $value): string => is_scalar($value) ? (string) $value : '',
			array_values($arr)
		);
	}

	/** Keep only customer-controlled booking and contact fields. */
	private static function sanitize_payload(array $payload): array
	{
		if (is_array($payload['payload_contract'] ?? null)) {
			$payload = $payload['payload_contract'];
		}

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
			return dy_sanitize_email((string) $value);
		}
		if ($field === 'inquiry') {
			return sanitize_textarea_field((string) $value);
		}
		return sanitize_text_field((string) $value);
	}

	private static function sanitize_service(array $service): array
	{
		$output = [];
		foreach (self::SERVICE_SCALAR_CONTRACT as $field) {
			$value = $service[$field] ?? null;
			if ($value === null || !is_scalar($value)) {
				continue;
			}
			$output[$field] = match ($field) {
				'dy_id' => absint($value),
				'dy_request' => sanitize_key((string) $value),
				'url' => esc_url_raw((string) $value),
				'total', 'payment_amount' => max(0, (float) $value),
				'description', 'confirmation_message' => sanitize_textarea_field((string) $value),
				default => sanitize_text_field((string) $value),
			};
		}

		$discount = is_array($service['discount'] ?? null) ? $service['discount'] : [];
		$output['discount'] = [
			'code' => sanitize_text_field((string) ($discount['code'] ?? '')),
			'percentage' => max(0, (float) ($discount['percentage'] ?? 0)),
			'amount' => max(0, (float) ($discount['amount'] ?? 0)),
		];
		$output['providers'] = self::sanitize_service_rows(
			$service['providers'] ?? [],
			['id', 'name', 'outstanding_balance', 'language', 'emails', 'whatsapp']
		);
		$output['add_ons'] = self::sanitize_service_rows(
			$service['add_ons'] ?? [],
			['id', 'price', 'name', 'description']
		);
		$output['itinerary'] = self::sanitize_service_rows(
			$service['itinerary'] ?? [],
			['icon', 'text']
		);
		return $output;
	}

	private static function sanitize_service_rows(mixed $rows, array $allowed): array
	{
		$output = [];
		foreach (is_array($rows) ? $rows : [] as $row) {
			$row = is_object($row) ? get_object_vars($row) : $row;
			if (!is_array($row)) {
				continue;
			}
			$clean = [];
			foreach ($allowed as $key) {
				$value = $row[$key] ?? null;
				if (is_array($value) && $key === 'emails') {
					$clean[$key] = array_values(array_filter(array_map(
						static fn(mixed $email): string => is_scalar($email) ? dy_sanitize_email((string) $email) : '',
						$value
					)));
				} elseif (is_scalar($value)) {
					$clean[$key] = in_array($key, ['price', 'outstanding_balance'], true)
						? (float) $value
						: sanitize_text_field((string) $value);
				}
			}
			if ($clean !== []) {
				$output[] = $clean;
			}
		}
		return $output;
	}

	private static function sanitize_gateway(mixed $gateway): array
	{
		$gateway = is_object($gateway) ? get_object_vars($gateway) : $gateway;
		if (!is_array($gateway) || $gateway === []) {
			return [];
		}

		$output = [
			'id' => sanitize_key((string) ($gateway['id'] ?? '')),
			'status' => sanitize_key((string) ($gateway['status'] ?? '')),
			'reference' => isset($gateway['reference']) && is_scalar($gateway['reference'])
				? sanitize_text_field((string) $gateway['reference'])
				: '',
		];
		$response = is_array($gateway['response'] ?? null) ? $gateway['response'] : [];
		$output['response'] = [];
		foreach (['status', 'code', 'message', 'text', 'reference'] as $key) {
			if (isset($response[$key]) && is_scalar($response[$key])) {
				$output['response'][$key] = sanitize_text_field((string) $response[$key]);
			}
		}

		$metadata = is_array($gateway['metadata'] ?? null) ? $gateway['metadata'] : [];
		$output['metadata'] = [];
		foreach (self::GATEWAY_METADATA_CONTRACT as $key) {
			$value = $metadata[$key] ?? null;
			if ($value === null || !is_scalar($value)) {
				continue;
			}
			$output['metadata'][$key] = match ($key) {
				'payment_url', 'qrcode_url', 'qrcode' => esc_url_raw((string) $value),
				'payment_amount', 'service_fee' => max(0, (float) $value),
				'debug_mode' => (int) $value,
				'network_id', 'network' => sanitize_key((string) $value),
				default => sanitize_text_field((string) $value),
			};
		}
		return $output;
	}

	/** Keep only conversion fields emitted by the server-side analytics queue. */
	private static function sanitize_conversion_events(mixed $events, string $tx_id): array
	{
		$output = [];
		foreach (is_array($events) ? $events : [] as $event) {
			$name = $event['name'] ?? '';
			$params = $event['params'] ?? [];
			if (!in_array($name, ['purchase', 'generate_lead'], true)
				|| !is_array($params)
				|| ($params['transaction_id'] ?? '') !== $tx_id) {
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
					if (!is_array($item)) {
						continue;
					}
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

	/** Normalize unexpired v1 transactions for validation and runtime rendering. */
	private static function normalize_transaction(mixed $value): array
	{
		$transaction = self::normalize_input_array($value);
		if ($transaction === []) {
			return [];
		}

		foreach (['booking_details', 'contact_details'] as $section) {
			$details = self::normalize_input_array($transaction[$section] ?? []);
			if ($details !== []) {
				$transaction = [...$transaction, ...$details];
			}
			unset($transaction[$section]);
		}

		if ((int) ($transaction['schema_version'] ?? 0) >= self::SCHEMA_VERSION) {
			$tx_id = is_scalar($transaction['tx_id'] ?? null)
				? (string) $transaction['tx_id']
				: '';
			$normalized = [
				'schema_version' => self::SCHEMA_VERSION,
				'status' => sanitize_key((string) ($transaction['status'] ?? 'started')),
				'tx_id' => $tx_id,
				'secret_tx_id' => is_scalar($transaction['secret_tx_id'] ?? null)
					? sanitize_text_field((string) $transaction['secret_tx_id'])
					: '',
				'payload_contract' => self::sanitize_payload(
					self::normalize_input_array($transaction['payload_contract'] ?? [])
				),
				'service_contract' => self::sanitize_service(
					self::normalize_input_array($transaction['service_contract'] ?? [])
				),
				'gateway' => self::sanitize_gateway($transaction['gateway'] ?? []),
				'events' => self::sanitize_conversion_events($transaction['events'] ?? [], $tx_id),
				'timestamps' => [
					'created_at' => sanitize_text_field((string) ($transaction['timestamps']['created_at'] ?? '')),
					'updated_at' => sanitize_text_field((string) ($transaction['timestamps']['updated_at'] ?? '')),
				],
			];
			$legacy = self::normalize_input_array($transaction['_legacy_confirmation'] ?? []);
			if ($legacy !== []) {
				$normalized['_legacy_confirmation'] = [
					'title' => sanitize_text_field((string) ($legacy['title'] ?? '')),
					'content' => wp_kses_post((string) ($legacy['content'] ?? '')),
					'excerpt' => sanitize_text_field((string) ($legacy['excerpt'] ?? '')),
				];
			}
			return $normalized;
		}

		$confirmation = self::normalize_input_array($transaction['confirmation'] ?? []);
		$service = self::sanitize_service([
			'dy_id' => $transaction['dy_id'] ?? 0,
			'dy_request' => $transaction['dy_request'] ?? '',
			'title' => $transaction['title'] ?? '',
			'url' => $transaction['url'] ?? '',
			'description' => $transaction['description'] ?? '',
			'location' => $transaction['location'] ?? '',
			'confirmation_message' => $transaction['confirmation_message'] ?? '',
			'timezone' => $transaction['timezone'] ?? '',
			'total' => $transaction['total'] ?? 0,
			'payment_amount' => $transaction['payment_amount'] ?? ($transaction['amount'] ?? 0),
			'currency' => $transaction['currency'] ?? ($transaction['currency_name'] ?? ''),
			'payment_type' => $transaction['payment_type'] ?? '',
			'discount' => $transaction['discount'] ?? [],
			'providers' => $transaction['providers'] ?? [],
			'add_ons' => $transaction['add_ons'] ?? [],
			'itinerary' => $transaction['itinerary'] ?? [],
		]);
		$legacy = [
			'schema_version' => self::SCHEMA_VERSION,
			'status' => sanitize_key((string) ($transaction['status'] ?? 'started')),
			'tx_id' => (string) ($transaction['tx_id'] ?? ''),
			'secret_tx_id' => (string) ($transaction['secret_tx_id'] ?? ''),
			'payload_contract' => self::sanitize_payload($transaction),
			'service_contract' => $service,
			'gateway' => self::sanitize_gateway($transaction['gateway'] ?? []),
			'events' => self::sanitize_conversion_events(
				$transaction['events'] ?? ($confirmation['events'] ?? []),
				(string) ($transaction['tx_id'] ?? '')
			),
			'timestamps' => self::normalize_input_array($transaction['timestamps'] ?? []),
		];
		if ($confirmation !== []) {
			$legacy['_legacy_confirmation'] = [
				'title' => sanitize_text_field((string) ($confirmation['title'] ?? '')),
				'content' => wp_kses_post((string) ($confirmation['content'] ?? '')),
				'excerpt' => sanitize_text_field((string) ($confirmation['excerpt'] ?? '')),
			];
		}
		return $legacy;
	}

	private static function normalize_input_array(mixed $value): array
	{
		return is_object($value)
			? get_object_vars($value)
			: (is_array($value) ? $value : []);
	}

	/** Build the customer payload from the active POST or GET request. */
	public static function get_sanitized_request_payload(): array
	{
		$cache_key = 'get_sanitized_request_payload';
		if (array_key_exists($cache_key, self::$cache)) {
			return self::$cache[$cache_key];
		}

		$fn = match (secure_server('REQUEST_METHOD')) {
			'POST' => 'secure_post',
			'GET' => 'secure_get',
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
				in_array($field, ['email', 'repeat_email'], true) => 'dy_sanitize_email',
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

	/** Return a sanitized request value, or the current stored payload while rendering. */
	public static function request_value(string $key): string|int|bool|null
	{
		if (self::$current_transaction !== null && self::has_payload_value($key)) {
			$value = self::payload_value($key);
			return is_string($value) || is_int($value) || is_bool($value) ? $value : null;
		}

		$payload = self::get_sanitized_request_payload();
		if (!array_key_exists($key, $payload)) {
			write_log(
				sprintf('dy_tx::request_value(): unknown sanitized payload key "%s".', $key),
				true,
				true,
				'payload_contract_error'
			);
			return null;
		}
		return $payload[$key] ?? null;
	}

	public static function all_dy_request_types(): array
	{
		$request_types = apply_filters('all_dy_request_types', ['contact']);
		if (!is_array($request_types)) {
			return ['contact'];
		}
		$request_types = array_filter(
			$request_types,
			static fn(mixed $request_type): bool => is_string($request_type) && $request_type !== ''
		);
		return array_values(array_unique(array_map('sanitize_key', $request_types)));
	}
}
