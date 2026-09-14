<?php

if ( ! defined( 'WPINC' ) ) {
	exit;
}

#[AllowDynamicProperties]
class dy_transactions
{
	public static ?object $transaction_obj = null;

	private const TRANSIENT_PREFIX = 'secret_tx_id_';

	private const PAYLOAD_FIELDS = [
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
		],
	];

	/**
	 * Create the initial transaction record.
	 */
	public static function create(string $unique_tx_id, array $arr = []): bool
	{
		$unique_tx_id = trim($unique_tx_id);

		if ($unique_tx_id === '') {
			return false;
		}

		$identity = self::identity_values($unique_tx_id, $arr);

		if (
			$identity['email'] === ''
			|| ! is_email($identity['email'])
			|| $identity['dy_request'] === ''
			|| $identity['dy_id'] <= 0
		) {
			return false;
		}

		$transaction = (object) [
			'unique_tx_id'    => $unique_tx_id,
			'secret_tx_id'    => self::sign_secret([
				$unique_tx_id,
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

		self::$transaction_obj = $transaction;

		return (bool) set_transient(
			self::transient_key($unique_tx_id),
			$transaction,
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
	public static function validate(string $unique_tx_id, array $expected_arr = []): bool
	{
		$unique_tx_id = trim($unique_tx_id);
		$transaction = self::get($unique_tx_id);

		if ($transaction === null) {
			return false;
		}

		if ($expected_arr === []) {
			$expected_arr = [
				$unique_tx_id,
				(string) secure_post('email', '', 'sanitize_email'),
				(string) secure_post('dy_request', '', 'sanitize_key'),
				(int) secure_post('dy_id', 0, 'absint'),
			];
		}

		if (array_key_exists('unique_tx_id', $expected_arr)) {
			$expected_unique_tx_id = $expected_arr['unique_tx_id'];
			if (! is_scalar($expected_unique_tx_id) || (string) $expected_unique_tx_id !== $unique_tx_id) {
				return false;
			}
		} elseif (count($expected_arr) >= 4) {
			$expected_values = array_values($expected_arr);
			if (! is_scalar($expected_values[0]) || (string) $expected_values[0] !== $unique_tx_id) {
				return false;
			}
		}

		$expected = self::identity_values($unique_tx_id, $expected_arr);
		$stored = [
			'unique_tx_id' => (string) ($transaction->unique_tx_id ?? ''),
			'email'       => (string) ($transaction->email ?? ''),
			'dy_request'  => (string) ($transaction->dy_request ?? ''),
			'dy_id'       => (int) ($transaction->dy_id ?? 0),
		];
		$expected_values = [
			$expected['unique_tx_id'],
			$expected['email'],
			$expected['dy_request'],
			$expected['dy_id'],
		];
		$stored_values = [
			$stored['unique_tx_id'],
			$stored['email'],
			$stored['dy_request'],
			$stored['dy_id'],
		];
		$stored_secret = (string) ($transaction->secret_tx_id ?? '');

		if ($stored_secret === '' || $expected_values !== $stored_values) {
			return false;
		}

		return hash_equals($stored_secret, self::sign_secret($expected_values));
	}

	/**
	 * Retrieve a transaction record from its transient.
	 */
	public static function get(string $unique_tx_id): ?object
	{
		$unique_tx_id = trim($unique_tx_id);

		if ($unique_tx_id === '') {
			return null;
		}

		$stored = get_transient(self::transient_key($unique_tx_id));

		if (! is_array($stored) && ! is_object($stored)) {
			return null;
		}

		$transaction = is_object($stored) ? $stored : (object) $stored;

		if ((string) ($transaction->unique_tx_id ?? '') !== $unique_tx_id) {
			return null;
		}

		self::$transaction_obj = $transaction;

		return $transaction;
	}

	/**
	 * Update a transaction status and, on success only, its whitelisted payload.
	 */
	public static function update(
		string $unique_tx_id = '',
		string $new_status = '',
		array $payload = [],
		int $expiration_in_seconds = 0
	): bool {
		$transaction = self::get($unique_tx_id);

		if ($transaction === null) {
			return false;
		}

		$current_status = (string) ($transaction->status ?? '');
		$new_status = $new_status !== '' ? sanitize_key($new_status) : $current_status;
		$request_type = (string) ($transaction->dy_request ?? '');
		$allowed_statuses = $request_type === 'paguelo_facil_on'
			? ['started', 'processing', 'success', 'declined', 'error']
			: ['started', 'success'];

		if (! in_array($new_status, $allowed_statuses, true)) {
			return false;
		}

		$transaction->status = $new_status;

		if ($new_status === 'success' && $payload !== []) {
			$sanitized_payload = self::sanitize_payload($payload);
			$transaction->booking_details = (object) array_merge(
				self::object_to_array($transaction->booking_details ?? null),
				$sanitized_payload['booking_details']
			);
			$transaction->contact_details = (object) array_merge(
				self::object_to_array($transaction->contact_details ?? null),
				$sanitized_payload['contact_details']
			);
		}

		$expiration = $expiration_in_seconds > 0
			? $expiration_in_seconds
			: ($new_status === 'success' ? DAY_IN_SECONDS : HOUR_IN_SECONDS);

		// Keep the in-memory record in sync before persisting it.
		self::$transaction_obj = $transaction;

		return (bool) set_transient(
			self::transient_key($unique_tx_id),
			$transaction,
			$expiration
		);
	}

	private static function transient_key(string $unique_tx_id): string
	{
		return self::TRANSIENT_PREFIX . $unique_tx_id;
	}

	/**
	 * Normalize either a positional identity array or named identity fields.
	 *
	 * @return array{unique_tx_id: string, email: string, dy_request: string, dy_id: int}
	 */
	private static function identity_values(string $unique_tx_id, array $arr): array
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
				'unique_tx_id' => $unique_tx_id,
				'email'       => is_scalar($email) ? sanitize_email((string) $email) : '',
				'dy_request'  => is_scalar($dy_request) ? sanitize_key((string) $dy_request) : '',
				'dy_id'       => is_scalar($dy_id) ? absint($dy_id) : 0,
			];
		}

		$values = array_values($arr);
		$starts_with_id = isset($values[0]) && (string) $values[0] === $unique_tx_id;
		$offset = $starts_with_id ? 1 : 0;
		$email = $values[$offset] ?? '';
		$dy_request = $values[$offset + 1] ?? '';
		$dy_id = $values[$offset + 2] ?? 0;

		return [
			'unique_tx_id' => $unique_tx_id,
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
			array_key_exists('unique_tx_id', $arr)
			|| array_key_exists('email', $arr)
			|| array_key_exists('dy_request', $arr)
			|| array_key_exists('dy_id', $arr)
		) {
			$arr = [
				$arr['unique_tx_id'] ?? '',
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

		foreach (self::PAYLOAD_FIELDS as $section => $fields) {
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
			return sanitize_email((string) $value);
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
}
