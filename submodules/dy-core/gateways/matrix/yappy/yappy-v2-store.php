<?php

if (!defined('WPINC')) exit;

/** Durable order mapping; transients are only a cache of the public transaction. */
final class Dy_Yappy_V2_Store
{
	private static bool $installed = false;
	private static bool $read_failed = false;

	public static function read_failed(): bool { return self::$read_failed; }

	private static function table(): string
	{
		global $wpdb;
		return $wpdb->prefix . 'dy_yappy_v2_orders';
	}

	public static function available(): bool
	{
		return self::$installed || (string) dy_get_option('yappy_v2_schema', '') === '1';
	}

	public static function install(): bool
	{
		if (self::available()) return true;
		global $wpdb;
		$table = self::table();
		$collation = $wpdb->get_charset_collate();
		if ($wpdb->query("CREATE TABLE IF NOT EXISTS `$table` (
			order_id varchar(15) NOT NULL,
			tx_id char(36) NOT NULL,
			record longtext NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (order_id), UNIQUE KEY tx_id (tx_id)
		) $collation") === false) return false;
		self::$installed = (bool) update_option('yappy_v2_schema', '1', false);
		return self::$installed;
	}

	public static function find(string $value, bool $by_transaction = false): ?array
	{
		self::$read_failed = false;
		if (!self::available()) return null;
		global $wpdb;
		$table = self::table();
		$field = $by_transaction ? 'tx_id' : 'order_id';
		$json = $wpdb->get_var($wpdb->prepare("SELECT record FROM `$table` WHERE $field = %s", $value));
		self::$read_failed = ($wpdb->last_error ?? '') !== '';
		$record = is_string($json) ? json_decode($json, true) : null;
		return is_array($record) ? $record : null;
	}

	public static function save(array $record, bool $insert = false): bool
	{
		global $wpdb;
		$json = wp_json_encode($record);
		if (!is_string($json)) return false;
		$data = ['record' => $json, 'updated_at' => gmdate('Y-m-d H:i:s')];
		$identity = ['order_id' => $record['order_id'], 'tx_id' => $record['tx_id']];
		// INSERT must fail on either unique-key collision; never replace another order.
		if ($insert) return $wpdb->insert(self::table(), [...$identity, ...$data]) === 1;
		$changed = $wpdb->update(self::table(), $data, $identity);
		if ($changed === false) return false;
		if ($changed > 0) return true;
		// Zero means unchanged OR missing; do not acknowledge a vanished record.
		$existing = self::find($record['tx_id'], true);
		return $existing !== null && $existing['order_id'] === $record['order_id'];
	}

	/** Use the same lock as checkout, including the initial submission. */
	public static function lock(string $tx_id, bool $release = false): bool
	{
		global $wpdb;
		$name = 'dy_submit_' . substr(hash('sha256', $wpdb->prefix . $tx_id), 0, 54);
		$sql = $release ? 'SELECT RELEASE_LOCK(%s)' : 'SELECT GET_LOCK(%s, 1)';
		return (string) $wpdb->get_var($wpdb->prepare($sql, $name)) === '1';
	}

	/** Encrypt private API credentials/session data; never include these in $tx. */
	public static function seal(array $value): string
	{
		$iv = random_bytes(12);
		$tag = '';
		$key = hash('sha256', wp_salt('auth'), true);
		$cipher = openssl_encrypt((string) wp_json_encode($value), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
		return is_string($cipher) ? base64_encode($iv . $tag . $cipher) : '';
	}

	public static function open(string $value): ?array
	{
		$bytes = base64_decode($value, true);
		if ($bytes === false || strlen($bytes) < 29) return null;
		$plain = openssl_decrypt(substr($bytes, 28), 'aes-256-gcm', hash('sha256', wp_salt('auth'), true),
			OPENSSL_RAW_DATA, substr($bytes, 0, 12), substr($bytes, 12, 16));
		$result = is_string($plain) ? json_decode($plain, true) : null;
		return is_array($result) ? $result : null;
	}

	public static function read_transaction(mixed $cached, string $tx_id): mixed
	{
		if (!wp_is_uuid($tx_id, 4)) return $cached;
		if (is_array($cached) && dy_tx::service_value('gateway_id', '', $cached) !== 'yappy_v2') return $cached;
		$record = self::find($tx_id, true);
		return $record['tx'] ?? $cached;
	}

	public static function persist_transaction(bool $ok, array $tx): bool
	{
		if (!$ok || dy_tx::service_value('gateway_id', '', $tx) !== 'yappy_v2') return $ok;
		$record = self::find($tx['tx_id'], true);
		if (self::$read_failed) return false;
		// The checkout processing claim precedes creation of the Yappy order record.
		if ($record === null) return true;
		$record['tx'] = $tx;
		if (in_array($tx['gateway']['status'] ?? '', ['approved', 'declined', 'cancelled', 'expired'], true)) {
			$record['state'] = $tx['gateway']['status'];
			$record['launch'] = '';
		}
		return self::save($record);
	}
}
