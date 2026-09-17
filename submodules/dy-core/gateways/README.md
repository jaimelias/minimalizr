# Shared checkout

This directory depends on WordPress and the canonical `dy-core` library. It has no Dynamicpackages helpers, post-type rules, booking calculations, emails, or webhooks. Load it through `dy-core/loader.php`; do not load a second nested copy of the library.

## Responsibilities

- `checkout.php`: source/gateway registry, signed identity validation, transaction lock, Turnstile submission check, processing claim, result persistence, notification dispatch and localized 303 redirect.
- `source.php`: the `Dy_Checkout_Source` interface implemented by the plugin that owns the item.
- `gateway.php` and `matrix/*/*.php`: merchant configuration, amount limits, gateway-specific inputs, payment transport and structured results. Each provider owns its settings and instructions. USDT and USDC share the stablecoin implementation.
- `form.php`, `partials/` and `checkout.js`: customer/payment inputs, supplied summary rows and runtime HTML slots, gateway selection and submission. Rendered HTML is never part of a transaction.
- `../e-commerce/transactions.php` and `confirmation-page.php`: transient contracts and the `/dy-tx/{uuid}` endpoint. Confirmation resolves the source's context and renders stored results without processing another payment.

## Integrating another plugin

Implement `Dy_Checkout_Source` and register one instance after loading the core:

```php
Dy_Checkout::register_source('membership', new Membership_Checkout());
```

The adapter validates the submitted item and customer choices, computes prices and any fee or deposit, prepares `payload_contract` and `service_contract`, handles its notifications/analytics, and resolves a `WP_Post` for confirmation. The confirmation post can be a virtual page when the item lives outside WordPress posts. Keep `accepts_submission()` restricted to the adapter's own POST target. Keep `validate_context()` consistent with that target. Source callbacks must not charge a gateway or send notifications before the engine invokes the corresponding phase.

`selection()` maps the submitted action to `intent` (`contact`, `estimate`, `payment`) and `gateway_id`. Contact and estimate requests never invoke a payment gateway. Gateway IDs need not match the source's action names. Dynamicpackages preserves its existing `dy_request` values through this mapping.

The form's `fields` must include `checkout_source`, `dy_id`, `dy_request`, `intent` and `gateway_id`. The historical names `dy_id` and `dy_request` remain transport aliases for the numeric context ID and source action. The signing route remains `POST /dy-core/tx-sign/{dy_id}`. It resolves the named adapter rather than assuming a packages post. The HMAC binds the source, context, intent, selected gateway and customer identity.

For payments, the source must supply authoritative `payment_amount`, `amount_minor`, `currency_exponent`, `currency` and `formatted_charge_amount`. `amount_minor` is the final charge including any source-applied fee. For example, USD 15.99 is `1599` with exponent `2`. The decimal `payment_amount` is retained for existing amount-limit policies and compatibility. Gateways convert the final amount to the provider's wire format; they never calculate booking totals, discounts or deposits.

`payload_fields()` and `sanitize_payload_value()` define extra customer fields. `sanitize_service()` explicitly allowlists and sanitizes the source's additional computed data. Do not return card credentials, tokens, arbitrary request fields or HTML from either callback. Core identity and payment fields override extension fields. Provider HTTP responses are reduced to safe status/reference fields and allowlisted metadata.

Render a form with `Dy_Checkout_Form::render($view)` and method buttons with `Dy_Checkout_Form::buttons($descriptors)`. Supply labels, action URL, hidden choices and optional trusted `card_notice`, `terms` and `inquiry` HTML. The renderer loads its shared browser dependencies. Current controls and Turnstile containers support one checkout form per page. Browser extensions can listen for `dy:checkout:prepare`, call `event.detail.waitUntil(promise)` to finish a price refresh, and listen for `dy:checkout:submit` for analytics.

`dy_gateway_settings_parent` controls the settings menu parent. Existing merchant option names remain unchanged. Dynamicpackages selects its original menu parent, coupon restrictions and gateway display/deposit policy in `includes/class-dynamicpackages-form-actions.php`.

## Persistence and retries

Root `status: success` means submission processing completed; an offline/link gateway can still have `gateway.status: pending`. A card approval uses `gateway.status: approved`. The persisted `processing` state prevents retries after an uncertain payment response. A completed submission never repeats payments or notifications. Notification delivery remains an at-most-once attempt after persistence; a process failure at that point requires operational follow-up.

Use `dy_tx::update($tx)` to persist a transaction, with an optional TTL in seconds as the second argument. Transactions must use the nested schema-v2 array with an explicit checkout source. Old records, signatures without a source, and stored confirmation HTML are unsupported. Clear existing transaction transients when deploying this change; their confirmation links will expire.

`dy_checkout_default_source` supplies the request/form source when `checkout_source` is absent. Stored transactions always use their own `service_contract.source` and never fall back to this filter. Direct calls to `dy_tx::create($id, $identity)` must include a registered `source` in the identity array.

See `tests/gateways.php` for a complete isolated membership adapter and `tests/checkout.php` for the Dynamicpackages regression suite.

## Yappy V2 (`yappy_v2`)

This is a separate gateway from `yappy_direct`. Configure **Yappy Online** under the source plugin's settings menu. It is disabled by default. Set its environment, merchant ID, exact registered HTTPS domain, Base64 IPN secret, amount limits and display policy. Sandbox and production credentials are separate. Secret inputs use `type="password"` and display their saved values masked in the admin form. Blank secret fields preserve the saved value. Sandbox testers must be enrolled with Yappy; test approvals do not send Dynamicpackages fulfillment notifications or purchase analytics.

The public callback is **GET `/wp-json/dy-core/gateways/yappy-v2`**. Use the absolute URL shown on the settings page; WordPress installations in a subdirectory or using plain permalinks have a different URL prefix. The gateway supplies `rest_url('dy-core/gateways/yappy-v2')` as `ipnUrl`. It must be publicly reachable over HTTPS, without authentication or a browser challenge. The handler authenticates the callback's `orderId`, `status`, `domain` and `hash`/`Hash` using Yappy's documented HMAC. It returns `{"success":true}` only after persistence, with retryable errors for lock/write failures.

Sandbox uses the same HTTPS checks as production. An `http://localhost` checkout fails before any API request. For an end-to-end test, use a public HTTPS test site or an HTTPS tunnel configured for WordPress, and register that site's exact domain with Yappy. The callback must reach the same installation that stores the order. WordPress must recognize the checkout request as HTTPS and generate an HTTPS REST URL. Behind a reverse proxy, configure HTTPS detection at the trusted proxy/server boundary; a browser padlock alone does not guarantee that WordPress detects HTTPS. Checkout HTTPS, callback HTTPS, currency and amount failures have separate messages.

### Lifecycle

1. The source calculates the final charge and validates the booking. Submission stores a pending transaction and redirects to `/dy-tx/{tx_id}`. No Yappy HTTP call is made here.
2. The confirmation GET renders the official `<btn-yappy>` component for the originating browser. Its click sends an authenticated POST to `/wp-json/dy-core/gateways/yappy-v2/start`.
3. The server validates the merchant and creates one order with a stable 15-character ID. Amounts come from `service_contract.amount_minor` with exponent `2`, in USD/PAB. The final net charge is sent as subtotal/total with zero additional discount/tax; the gateway never reapplies booking discounts or deposit calculations. The stored contact phone must be eight digits with calling code `507`.
4. Only the owning browser receives `transactionId`, `token`, and `documentName` for `eventPayment()`. Access uses a random HTTP-only cookie and a cookie-bound CSRF header, expires after one day, and does not depend on the customer's IP address.
5. A verified IPN maps `E/R/C/X` to `approved/declined/cancelled/expired`. Approval cannot be downgraded. A delayed approval can resolve an earlier negative/uncertain result. The browser polls `/wp-json/dy-core/gateways/yappy-v2/status`; SDK success/error events never settle the payment.

### Persistence and recovery

The first validated submission creates `{wp_prefix}dy_yappy_v2_orders`. This durable table stores the transaction snapshot, order mapping, environment/domain and delivery state. Clearing transients does **not** erase Yappy orders or their confirmation pages. The `dy_tx_read_transaction` and `dy_tx_persist_transaction` filters keep this record authoritative while preserving the normal `dy_tx::update()` contract.

The IPN signing key snapshot and the short-lived SDK launch data are encrypted with AES-256-GCM using a key derived from the WordPress auth salt. They never enter `$tx`, public checkout HTML, logs, email or webhooks. The merchant authorization token is not stored. Changing configured merchant secrets does not break already submitted orders; rotating WordPress salts invalidates their signatures/private data. Keep the database and salts together in backups.

Creation is serialized with the checkout transaction lock. A second click resumes the same saved SDK session for up to five minutes; this is a local retention limit, not a documented Yappy token TTL. A timeout, interrupted request, duplicate-order response, or expired session never creates another order automatically. A signed IPN can still resolve it. The supplied API document has no status/reconciliation endpoint or guaranteed callback retry schedule, so unresolved orders require checking Yappy's merchant portal before arranging another payment.

`dy_gateway_settled_transaction` lets a source add its saved analytics data before settlement is persisted. `dy_gateway_payment_approved` lets it send its completion notifications afterward. Callbacks must check `service_contract.source` and environment. Dynamicpackages implements these hooks in `includes/class-dynamicpackages-form-actions.php`, retaining its pricing, providers, add-ons, booking email and paid webhook there.

Paid notifications are an **at-most-once attempt**, matching the existing checkout guarantee. The record is claimed before the external calls. A crash or failed email/webhook delivery after that claim needs manual follow-up; callback retries never resend automatically. Durable records include personal booking data and are not automatically purged: include this table in the site's retention/deletion procedure, retaining pending records until reconciled.

Run `php tests/yappy-v2.php` and `node tests/yappy-v2-browser.js`. These mock database, HTTP, SDK and email behavior. A real sandbox payment, public HTTPS callback delivery, and actual concurrent database connections remain deployment checks.
