# Changelog

All notable changes to `treasurer` will be documented in this file.

## [1.0.0] - 2026-05-10

### Added

- Capability-based gateway contracts: `Gateway`, `SupportsPayments`,
  `SupportsRefunds`, `SupportsCancellation`, `SupportsWebhooks`. Drivers
  implement only the surfaces they actually support; the service layer
  guards against missing capabilities with `UnsupportedFeatureException`.
- Built-in drivers for **GoPay** (`gopay`) and **Comgate** (`comgate`),
  covering simple one-shot payments, refunds, cancellation (where the
  gateway permits), and webhook reception.
- Gateway-agnostic DTOs: `PaymentRequest`, `PaymentResponse`,
  `RefundRequest`, `RefundResponse`. Money is integer minor units;
  provider-specific extras flow through `array $metadata`. Optional
  `?string $country` on `PaymentRequest` for customer routing.
- `Currency`, `Language`, `PaymentMethod` enums with `gopay()` /
  `comgate()` translation helpers.
- Eloquent persistence: `payments`, `refunds`, `payment_events`
  migrations; `Payment`, `Refund`, `PaymentEvent` models with UUID PKs.
- Lifecycle events: `PaymentCreated`, `PaymentPending`, `PaymentPaid`,
  `PaymentCancelled`, `PaymentFailed`, `PaymentExpired`, `RefundCreated`,
  `RefundSucceeded`, `RefundFailed`.
- `PaymentService::create/refresh` and `RefundService::create` for
  one-call payments/refunds with persistence + events. The refresh path
  also finalises pending GoPay refunds when the parent payment is
  reported as `REFUNDED` / `PARTIALLY_REFUNDED`.
- `GatewayRegistry` for registering built-in and host-provided drivers,
  resolved into a singleton `Gateway` binding driven by
  `config('treasurer.default')`.
- `Th3JK\Treasurer\Treasurer` entry point + `Treasurer` facade
  auto-discovery.
- Webhook receiver mounted at
  `POST|GET /treasurer/webhooks/{gateway}/{token}` (named
  `treasurer.webhook`). Verifies the URL token, delegates signature
  verification + event parsing to the driver, then reconciles via
  `PaymentService::refresh`.
- Documentation in `docs/`: installation, configuration, usage,
  architecture, extending.
- Test suite (PHPUnit 11 + Orchestra Testbench 11 + sqlite in-memory)
  covering enums, registry, drivers, services, and the webhook
  controller.

### Known limitations

- Comgate refund finalization is not yet automatic. The vendor SDK
  exposes no refund-status endpoint and no separate refund webhook
  payload for online payments. Refunds against Comgate remain in
  `RefundState::REQUESTED` until a future iteration adds reconciliation.
- Recurring/subscription payments and preauth/capture flows are not yet
  implemented. Their seams (`SupportsRecurring`, `SupportsPreauth`) are
  reserved for a future release.
