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
		'source',
		'intent',
		'gateway_id',
		'context_id',
		'amount_minor',
		'currency_exponent',
		'formatted_charge_amount',
		'total',
		'payment_amount',
		'formatted_payment_amount',
		'currency',
		'payment_type',
	];

	private const GATEWAY_METADATA_CONTRACT = [
		'order_id',
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

		$source = sanitize_key((string) ($arr['source'] ?? ''));
		$adapter = Dy_Checkout::source($source);
		if ($adapter === null) return false;
		$service = [
			'dy_id' => $identity['dy_id'], 'dy_request' => $identity['dy_request'],
			'source' => $source, 'context_id' => $identity['dy_id'],
			...$adapter->selection($identity['dy_request']),
		];
		$now = gmdate('c');
		$tx = [
			'schema_version' => self::SCHEMA_VERSION,
			'status' => 'started',
			'tx_id' => $tx_id,
			'secret_tx_id' => '',
			'payload_contract' => self::sanitize_payload($arr, $source),
			'service_contract' => $service,
			'gateway' => [],
			'events' => [],
			'timestamps' => [
				'created_at' => $now,
				'updated_at' => $now,
			],
		];
		$tx['payload_contract']['email'] = $identity['email'];
		$tx['secret_tx_id'] = self::signature($tx);

		return (bool) set_transient(
			self::transient_key($tx_id),
			$tx,
			HOUR_IN_SECONDS
		);
	}

	private static function signature(array $tx): string
	{
		$service = self::service($tx);
		$identity = self::identity_values((string) $tx['tx_id'], $tx);
		return hash_hmac('sha256', (string) wp_json_encode([
			...array_values($identity), $service['source'], $service['context_id'] ?? 0,
			$service['intent'] ?? '', $service['gateway_id'] ?? '',
		]), wp_salt('auth'));
	}

	/** Validate the signature and immutable identity fields. */
	public static function validate(string $tx_id, array $expected_arr = []): bool
	{
		$tx_id = trim($tx_id);
		$tx = self::get_stored_tx($tx_id);
		if ($tx === null) {
			return false;
		}

		$from_request = $expected_arr === [];
		if ($from_request) {
			$expected_arr = [
				'tx_id' => $tx_id,
				'email' => (string) self::request_value('email'),
				'dy_request' => (string) secure_post('dy_request', '', 'sanitize_key'),
				'dy_id' => (int) secure_post('dy_id', 0, 'absint'),
			];
		}

		$expected_id = $expected_arr['tx_id'] ?? null;
		if (!is_scalar($expected_id) || (string) $expected_id !== $tx_id) return false;

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

		$stored_service = self::service($tx);
		$expected_source = $from_request ? Dy_Checkout::source_id() : Dy_Checkout::source_id($expected_arr);
		if ($expected_source !== $stored_service['source']) return false;
		if (!$from_request) {
			foreach (['source', 'context_id', 'intent', 'gateway_id'] as $key) {
				if (($expected_arr['service_contract'][$key] ?? null) !== ($stored_service[$key] ?? null)) return false;
			}
		}
		return $stored_secret !== '' 
			&& $expected_values === $stored_values
			&& hash_equals($stored_secret, self::signature($tx));
	}

	/** Retrieve and normalize a transaction transient. */
	public static function get_stored_tx(string $tx_id): ?array
	{
		$tx_id = trim($tx_id);
		if ($tx_id === '') {
			return null;
		}

		$stored = apply_filters('dy_tx_read_transaction', get_transient(self::transient_key($tx_id)), $tx_id);
		if (!is_array($stored)) {
			return null;
		}

		$tx = self::normalize_transaction($stored);
		return (string) ($tx['tx_id'] ?? '') === $tx_id ? $tx : null;
	}

	/** Persist a structured transaction; only explicit contract fields reach the transient. */
	public static function update(array $submitted, int $expiration_in_seconds = 0): bool
	{
		if (($submitted['schema_version'] ?? null) !== self::SCHEMA_VERSION) return false;
		$tx_id = (string) ($submitted['tx_id'] ?? '');
		$stored = self::get_stored_tx($tx_id);
		if ($stored === null || !self::validate($tx_id, $submitted)) {
			return false;
		}

		$new_status = (string) ($submitted['status'] ?? '');
		$current_status = sanitize_key((string) ($stored['status'] ?? 'started'));
		$new_status = $new_status !== '' ? sanitize_key($new_status) : $current_status;
		$allowed_statuses = ['started', 'processing', 'success', 'declined', 'error'];

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
		$incoming_payload = self::sanitize_payload((array) ($submitted['payload_contract'] ?? []), Dy_Checkout::source_id($stored));
		$incoming_service = self::sanitize_service((array) ($submitted['service_contract'] ?? []));

		$payload_contract = [...$stored_payload, ...$incoming_payload];
		$service_contract = [...$stored_service, ...$incoming_service];
		$stored_identity = self::identity_values($tx_id, $stored);
		$payload_contract['email'] = $stored_identity['email'];
		$service_contract['dy_id'] = $stored_identity['dy_id'];
		$service_contract['dy_request'] = $stored_identity['dy_request'];
		foreach (['source', 'context_id', 'intent', 'gateway_id'] as $key) {
			if (array_key_exists($key, $stored_service)) $service_contract[$key] = $stored_service[$key];
		}

		$gateway = array_key_exists('gateway', $submitted)
			? self::sanitize_gateway($submitted['gateway'])
			: self::gateway($stored);
		$events = array_key_exists('events', $submitted)
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

		// Asynchronous gateways can keep an authoritative, durable copy before caching it.
		if (!apply_filters('dy_tx_persist_transaction', true, $tx)) return false;
		return (bool) set_transient(self::transient_key($tx_id), $tx, $expiration);
	}

	/** Set the structured transaction used while rendering a confirmation GET. */
	public static function set_current_transaction(?array $tx): void
	{
		self::$current_transaction = $tx === null ? null : self::normalize_transaction($tx);
		foreach (array_keys(self::$cache) as $key) {
			if (str_starts_with($key, 'get_sanitized_request_payload')) unset(self::$cache[$key]);
		}
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

	/** Keep only customer-controlled booking and contact fields. */
	private static function sanitize_payload(array $payload, string $source = ''): array
	{
		$output = [];
		foreach (self::payload_fields($source) as $field) {
			$value = $payload[$field] ?? null;
			if ($value !== null && is_scalar($value)) {
				$output[$field] = self::sanitize_payload_value($field, $value, $source);
			}
		}
		return $output;
	}

	private static function payload_fields(string $source = ''): array
	{
		$adapter = Dy_Checkout::source($source ?: Dy_Checkout::source_id());
		return array_unique([...self::CONTACT_CONTRACT, ...($adapter?->payload_fields() ?? [])]);
	}

	private static function sanitize_payload_value(string $field, mixed $value, string $source = ''): string|int|bool
	{
		if (in_array($field, ['email', 'repeat_email'], true)) return dy_sanitize_email((string) $value);
		if ($field === 'inquiry') return sanitize_textarea_field((string) $value);
		$adapter = Dy_Checkout::source($source ?: Dy_Checkout::source_id());
		return $adapter !== null ? $adapter->sanitize_payload_value($field, $value) : sanitize_text_field((string) $value);
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
				'dy_id', 'context_id', 'amount_minor', 'currency_exponent' => absint($value),
				'dy_request' => sanitize_key((string) $value),
				'url' => esc_url_raw((string) $value),
				'total', 'payment_amount' => max(0, (float) $value),
				'description', 'confirmation_message' => sanitize_textarea_field((string) $value),
				default => sanitize_text_field((string) $value),
			};
		}

		$adapter = Dy_Checkout::source((string) ($output['source'] ?? Dy_Checkout::source_id()));
		return $adapter !== null ? [...$adapter->sanitize_service($service), ...$output] : $output;
	}

	private static function sanitize_gateway(mixed $gateway): array
	{
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

	/** Sanitize the current transaction schema for validation and runtime rendering. */
	private static function normalize_transaction(array $transaction): array
	{
		if (($transaction['schema_version'] ?? null) !== self::SCHEMA_VERSION
			|| !is_array($transaction['payload_contract'] ?? null)
			|| !is_array($transaction['service_contract'] ?? null)
			|| Dy_Checkout::source_id($transaction) === '') {
			return [];
		}

		$tx_id = is_scalar($transaction['tx_id'] ?? null) ? (string) $transaction['tx_id'] : '';
		return [
			'schema_version' => self::SCHEMA_VERSION,
			'status' => sanitize_key((string) ($transaction['status'] ?? 'started')),
			'tx_id' => $tx_id,
			'secret_tx_id' => is_scalar($transaction['secret_tx_id'] ?? null)
				? sanitize_text_field((string) $transaction['secret_tx_id']) : '',
			'payload_contract' => self::sanitize_payload($transaction['payload_contract'], Dy_Checkout::source_id($transaction)),
			'service_contract' => self::sanitize_service($transaction['service_contract']),
			'gateway' => self::sanitize_gateway($transaction['gateway'] ?? []),
			'events' => self::sanitize_conversion_events($transaction['events'] ?? [], $tx_id),
			'timestamps' => [
				'created_at' => sanitize_text_field((string) ($transaction['timestamps']['created_at'] ?? '')),
				'updated_at' => sanitize_text_field((string) ($transaction['timestamps']['updated_at'] ?? '')),
			],
		];
	}

	/** Build the customer payload from the active POST or GET request. */
	public static function get_sanitized_request_payload(): array
	{
		$cache_key = 'get_sanitized_request_payload_' . Dy_Checkout::source_id();
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
		foreach (self::payload_fields() as $field) {
			$request_payload[$field] = self::sanitize_payload_value($field, $getter($field, ''));
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
