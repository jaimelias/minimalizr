<?php

if (!defined('WPINC')) exit;

/** Business rules supplied by the plugin that owns the item being purchased. */
interface Dy_Checkout_Source
{
	public function accepts_submission(): bool;
	public function matches_submission(?array $tx): bool;
	public function validate_submission(): bool;
	public function validate_context(int $id, string $request): bool;
	public function selection(string $request): array;
	public function prepare_transaction(array $tx): array;
	public function processed_transaction(array $tx): array;
	public function notify_transaction(array $tx): void;
	public function confirmation_post(array $tx): ?WP_Post;
	public function confirmation_result(mixed $result, array $tx): array;
	public function payload_fields(): array;
	public function sanitize_payload_value(string $field, mixed $value): string|int|bool;
	public function sanitize_service(array $service): array;
}
