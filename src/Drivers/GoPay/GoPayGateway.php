<?php

namespace Th3JK\Treasurer\Drivers\GoPay;

use GoPay\Definition\Response\PaymentStatus;
use GoPay\Http\Log\Logger as GoPayLogger;
use GoPay\Http\Response;
use GoPay\Payments;
use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Th3JK\Treasurer\Contracts\Gateway;
use Th3JK\Treasurer\Contracts\SupportsCancellation;
use Th3JK\Treasurer\Contracts\SupportsPayments;
use Th3JK\Treasurer\Contracts\SupportsRefunds;
use Th3JK\Treasurer\Contracts\SupportsWebhooks;
use Th3JK\Treasurer\DTOs\PaymentRequest;
use Th3JK\Treasurer\DTOs\PaymentResponse;
use Th3JK\Treasurer\DTOs\RefundRequest;
use Th3JK\Treasurer\DTOs\RefundResponse;
use Th3JK\Treasurer\Enums\Currency;
use Th3JK\Treasurer\Enums\PaymentState;
use Th3JK\Treasurer\Enums\RefundState;
use Th3JK\Treasurer\Enums\WebhookEventKind;
use Th3JK\Treasurer\Exceptions\GatewayException;
use Th3JK\Treasurer\Webhooks\WebhookEvent;

class GoPayGateway implements Gateway, SupportsCancellation, SupportsPayments, SupportsRefunds, SupportsWebhooks
{
    public const NAME = 'gopay';

    private const SANDBOX_URL = 'https://gw.sandbox.gopay.com/api';

    private const PRODUCTION_URL = 'https://gate.gopay.cz/api';

    private ?Payments $payments = null;

    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config) {}

    public function getName(): string
    {
        return self::NAME;
    }

    public function createPayment(PaymentRequest $request): PaymentResponse
    {
        $payload = [
            'amount' => $request->amountInCents,
            'currency' => $request->currency->gopay(),
            'order_number' => $request->referenceId,
            'order_description' => $request->description,
            'lang' => $request->language->gopay(),
            'callback' => [
                'return_url' => $request->returnUrl,
                'notification_url' => $request->notificationUrl,
            ],
        ];

        if ($request->email !== null) {
            $payload['payer']['contact']['email'] = $request->email;
        }

        if ($request->country !== null) {
            $payload['payer']['contact']['country_code'] = $request->country;
        }

        if ($request->method !== null) {
            $instrument = $request->method->gopay();
            $payload['payer']['default_payment_instrument'] = $instrument;
            $payload['payer']['allowed_payment_instruments'] = [$instrument];
        }

        $response = $this->payments()->createPayment($payload);
        $this->ensureSuccess($response, 'create payment');

        return new PaymentResponse(
            paymentId: (string) $response->json['id'],
            state: $this->mapState($response->json['state'] ?? null),
            amountInCents: (int) ($response->json['amount'] ?? $request->amountInCents),
            currency: $request->currency,
            provider: self::NAME,
            redirectUrl: $response->json['gw_url'] ?? null,
            method: $request->method,
            raw: $response->json ?? [],
        );
    }

    public function getPayment(string $paymentId): PaymentResponse
    {
        $response = $this->payments()->getStatus($paymentId);
        $this->ensureSuccess($response, 'fetch payment');

        $currencyCode = $response->json['currency'] ?? null;
        $currency = $currencyCode !== null ? $this->currencyFromCode((string) $currencyCode) : Currency::CZK;

        return new PaymentResponse(
            paymentId: (string) ($response->json['id'] ?? $paymentId),
            state: $this->mapState($response->json['state'] ?? null),
            amountInCents: (int) ($response->json['amount'] ?? 0),
            currency: $currency,
            provider: self::NAME,
            redirectUrl: $response->json['gw_url'] ?? null,
            raw: $response->json ?? [],
        );
    }

    public function refundPayment(string $paymentId, RefundRequest $request): RefundResponse
    {
        $response = $this->payments()->refundPayment($paymentId, $request->amountInCents);
        $this->ensureSuccess($response, 'refund payment');

        return new RefundResponse(
            state: $this->mapRefundResult($response->json['result'] ?? null),
            amountInCents: $request->amountInCents,
            provider: self::NAME,
            refundId: isset($response->json['id']) ? (string) $response->json['id'] : null,
            raw: $response->json ?? [],
        );
    }

    public function cancelPayment(string $paymentId): bool
    {
        return $this->payments()->voidAuthorization($paymentId)->hasSucceed();
    }

    public function verifySignature(Request $request): bool
    {
        // GoPay does not sign webhook notifications. The notification URL
        // path itself carries a per-installation secret token, which is
        // verified by WebhookController before this method is called.
        return true;
    }

    public function parseEvent(Request $request): ?WebhookEvent
    {
        $id = $request->query('id') ?? $request->input('id');

        if (! is_string($id) && ! is_numeric($id)) {
            return null;
        }

        $id = (string) $id;
        if ($id === '') {
            return null;
        }

        return new WebhookEvent(
            kind: WebhookEventKind::PAYMENT_NOTIFICATION,
            paymentId: $id,
            raw: $request->all(),
        );
    }

    private function payments(): Payments
    {
        if ($this->payments !== null) {
            return $this->payments;
        }

        $services = [];
        $logger = $this->resolveLogger();

        if ($logger !== null) {
            $services['logger'] = $logger;
        }

        return $this->payments = \GoPay\payments([
            'goid' => $this->config['credentials']['goid'] ?? null,
            'clientId' => $this->config['credentials']['client_id'] ?? null,
            'clientSecret' => $this->config['credentials']['client_secret'] ?? null,
            'gatewayUrl' => $this->resolveGatewayUrl(),
            'language' => $this->config['options']['language'] ?? 'EN',
            'timeout' => (int) ($this->config['options']['timeout'] ?? 30),
        ], $services);
    }

    /**
     * Resolve an optional HTTP logger for the GoPay SDK. Returns null unless
     * treasurer.debug is enabled AND the host application has bound a
     * GoPay\Http\Log\Logger implementation. Without a logger the SDK falls
     * back to its default NullLogger.
     */
    private function resolveLogger(): ?GoPayLogger
    {
        $container = Container::getInstance();

        if (! $container->bound('config')) {
            return null;
        }

        if (! (bool) $container->make('config')->get('treasurer.debug', false)) {
            return null;
        }

        if (! $container->bound(GoPayLogger::class)) {
            return null;
        }

        $logger = $container->make(GoPayLogger::class);

        return $logger instanceof GoPayLogger ? $logger : null;
    }

    private function resolveGatewayUrl(): string
    {
        return ($this->config['environment'] ?? 'sandbox') === 'production'
            ? self::PRODUCTION_URL
            : self::SANDBOX_URL;
    }

    private function ensureSuccess(Response $response, string $action): void
    {
        if ($response->hasSucceed()) {
            return;
        }

        $message = is_array($response->json) && isset($response->json['errors'][0]['message'])
            ? (string) $response->json['errors'][0]['message']
            : "GoPay failed to {$action}.";

        throw new GatewayException(
            message: $message,
            provider: self::NAME,
            statusCode: $response->statusCode,
            body: is_array($response->json) ? $response->json : null,
        );
    }

    private function mapState(?string $native): PaymentState
    {
        return match ($native) {
            PaymentStatus::CREATED => PaymentState::CREATED,
            PaymentStatus::PAYMENT_METHOD_CHOSEN, PaymentStatus::AUTHORIZED => PaymentState::PENDING,
            PaymentStatus::PAID, PaymentStatus::REFUNDED, PaymentStatus::PARTIALLY_REFUNDED => PaymentState::PAID,
            PaymentStatus::CANCELED => PaymentState::CANCELLED,
            PaymentStatus::TIMEOUTED => PaymentState::EXPIRED,
            default => PaymentState::PENDING,
        };
    }

    private function mapRefundResult(?string $result): RefundState
    {
        return match ($result) {
            'FINISHED' => RefundState::SUCCESS,
            'ACCEPTED' => RefundState::REQUESTED,
            null => RefundState::REQUESTED,
            default => RefundState::FAILED,
        };
    }

    private function currencyFromCode(string $code): Currency
    {
        return Currency::tryFrom($code) ?? Currency::CZK;
    }
}
