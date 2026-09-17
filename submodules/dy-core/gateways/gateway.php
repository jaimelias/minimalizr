<?php

if (!defined('WPINC')) exit;

/** A gateway receives an authoritative service contract; it never prices an order. */
#[AllowDynamicProperties]
abstract class Dy_Gateway
{
	public string $id;
	protected array $transaction = [];

	protected function register(string $id): void
	{
		$this->id = $id;
		$this->plugin_id = (string) apply_filters('dy_gateway_settings_parent', 'dy-core');
		add_action('init', [$this, 'init']);
		add_action('admin_init', [$this, 'settings_init'], 1);
		add_action('admin_menu', [$this, 'add_settings_page'], 100);
		Dy_Checkout::register_gateway($this);
	}

	public function descriptor(): array
	{
		return [
			'id' => $this->id, 'name' => $this->name, 'type' => $this->type,
			'color' => $this->color, 'background_color' => $this->background_color,
			'brands' => $this->brands, 'branding' => $this->branding(), 'icon' => $this->icon,
			'networks' => $this->enabled_networks ?? [],
			'min' => (float) ($this->min ?? 0), 'max' => (float) ($this->max ?? 0),
			'show' => (int) ($this->show ?? 0), 'service_fee' => (float) ($this->service_fee ?? 0),
		];
	}

	public function available(array $service): bool
	{
		$amount = (float) ($service['payment_amount'] ?? 0);
		return $this->is_active() && is_finite($amount) && $amount > 0
			&& $amount >= (float) ($this->min ?? 0)
			&& $amount <= (float) ($this->max ?? 0);
	}

	public function validate(array $tx): bool
	{
		if (!$this->available(dy_tx::service($tx))) {
			dy_errors::add(__('The selected payment method is unavailable.', 'dycore'));
			return false;
		}
		return true;
	}

	/** amount_minor is calculated by the source adapter, in the supplied currency. */
	protected function amount(array $tx): float
	{
		$service = dy_tx::service($tx);
		return isset($service['amount_minor'])
			? (int) $service['amount_minor'] / (10 ** (int) ($service['currency_exponent'] ?? 2))
			: (float) ($service['payment_amount'] ?? 0);
	}

	abstract public function process_transaction(array $tx): array;
	abstract public function confirmation_result(array $result, array $tx): array;
}
