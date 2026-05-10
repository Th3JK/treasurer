<?php

namespace Th3JK\Treasurer\Tests\Unit\Drivers;

use GoPay\Definition\Response\PaymentStatus;
use GoPay\Http\Response;
use GoPay\Payments;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Th3JK\Treasurer\Contracts\SupportsCancellation;
use Th3JK\Treasurer\Contracts\SupportsPayments;
use Th3JK\Treasurer\Contracts\SupportsRefunds;
use Th3JK\Treasurer\Contracts\SupportsWebhooks;
use Th3JK\Treasurer\Drivers\GoPay\GoPayGateway;
use Th3JK\Treasurer\DTOs\PaymentRequest;
use Th3JK\Treasurer\DTOs\RefundRequest;
use Th3JK\Treasurer\Enums\Currency;
use Th3JK\Treasurer\Enums\Language;
use Th3JK\Treasurer\Enums\PaymentMethod;
use Th3JK\Treasurer\Enums\PaymentState;
use Th3JK\Treasurer\Enums\RefundState;
use Th3JK\Treasurer\Enums\WebhookEventKind;
use Th3JK\Treasurer\Exceptions\GatewayException;

class GoPayGatewayTest extends TestCase
{
    #[Test]
    public function it_implements_the_payment_capability_interfaces(): void
    {
        $gateway = new GoPayGateway($this->config());

        $this->assertInstanceOf(SupportsPayments::class, $gateway);
        $this->assertInstanceOf(SupportsRefunds::class, $gateway);
        $this->assertInstanceOf(SupportsCancellation::class, $gateway);
        $this->assertInstanceOf(SupportsWebhooks::class, $gateway);
        $this->assertSame('gopay', $gateway->getName());
    }

    #[Test]
    public function it_forwards_country_into_payer_contact_when_provided(): void
    {
        $gateway = new GoPayGateway($this->config());
        $fake = $this->fakePayments(create: $this->goPayResponse(200, [
            'id' => 1,
            'state' => PaymentStatus::CREATED,
            'amount' => 1,
            'gw_url' => 'https://...',
        ]));
        $this->injectPayments($gateway, $fake);

        $request = new PaymentRequest(
            referenceId: 'ORDER-2',
            amountInCents: 100,
            currency: Currency::EUR,
            description: 'Test',
            language: Language::EN,
            returnUrl: 'https://example.test/return',
            notificationUrl: 'https://example.test/notify',
            country: 'DE',
        );

        $gateway->createPayment($request);

        $this->assertSame('DE', $fake->lastCreatePayload['payer']['contact']['country_code'] ?? null);
    }

    #[Test]
    public function it_omits_country_when_not_provided(): void
    {
        $gateway = new GoPayGateway($this->config());
        $fake = $this->fakePayments(create: $this->goPayResponse(200, [
            'id' => 1,
            'state' => PaymentStatus::CREATED,
            'amount' => 1,
            'gw_url' => 'https://...',
        ]));
        $this->injectPayments($gateway, $fake);

        $gateway->createPayment($this->paymentRequest());

        $this->assertArrayNotHasKey('country_code', $fake->lastCreatePayload['payer']['contact'] ?? []);
    }

    #[Test]
    public function it_verifies_signature_unconditionally_since_gopay_does_not_sign(): void
    {
        $gateway = new GoPayGateway($this->config());

        $this->assertTrue($gateway->verifySignature(Request::create('/webhook', 'GET')));
    }

    #[Test]
    public function it_parses_a_webhook_event_from_the_id_query_parameter(): void
    {
        $gateway = new GoPayGateway($this->config());

        $event = $gateway->parseEvent(Request::create('/webhook?id=9876543', 'GET'));

        $this->assertNotNull($event);
        $this->assertSame(WebhookEventKind::PAYMENT_NOTIFICATION, $event->kind);
        $this->assertSame('9876543', $event->paymentId);
    }

    #[Test]
    public function it_returns_null_when_webhook_has_no_id(): void
    {
        $gateway = new GoPayGateway($this->config());

        $this->assertNull($gateway->parseEvent(Request::create('/webhook', 'GET')));
    }

    #[Test]
    public function it_creates_a_payment_and_maps_the_response(): void
    {
        $gateway = new GoPayGateway($this->config());
        $this->injectPayments($gateway, $this->fakePayments(create: $this->goPayResponse(200, [
            'id' => 9876543,
            'state' => PaymentStatus::CREATED,
            'amount' => 19900,
            'gw_url' => 'https://gw.sandbox.gopay.com/checkout/1',
        ])));

        $response = $gateway->createPayment($this->paymentRequest());

        $this->assertSame('9876543', $response->paymentId);
        $this->assertSame(PaymentState::CREATED, $response->state);
        $this->assertSame(19900, $response->amountInCents);
        $this->assertSame(Currency::CZK, $response->currency);
        $this->assertSame('https://gw.sandbox.gopay.com/checkout/1', $response->redirectUrl);
        $this->assertSame('gopay', $response->provider);
        $this->assertSame(PaymentMethod::CARD, $response->method);
    }

    #[Test]
    public function it_maps_pending_states_when_fetching_a_payment(): void
    {
        $gateway = new GoPayGateway($this->config());
        $this->injectPayments($gateway, $this->fakePayments(status: $this->goPayResponse(200, [
            'id' => 42,
            'state' => PaymentStatus::PAYMENT_METHOD_CHOSEN,
            'amount' => 5000,
            'currency' => 'EUR',
        ])));

        $response = $gateway->getPayment('42');

        $this->assertSame(PaymentState::PENDING, $response->state);
        $this->assertSame(Currency::EUR, $response->currency);
        $this->assertSame(5000, $response->amountInCents);
    }

    #[Test]
    public function it_maps_paid_state_for_finalised_payments(): void
    {
        $gateway = new GoPayGateway($this->config());
        $this->injectPayments($gateway, $this->fakePayments(status: $this->goPayResponse(200, [
            'id' => 'p-1',
            'state' => PaymentStatus::PAID,
            'amount' => 1000,
            'currency' => 'CZK',
        ])));

        $this->assertSame(PaymentState::PAID, $gateway->getPayment('p-1')->state);
    }

    #[Test]
    public function it_maps_cancelled_and_expired_native_states(): void
    {
        $gateway = new GoPayGateway($this->config());
        $this->injectPayments($gateway, $this->fakePayments(status: $this->goPayResponse(200, [
            'id' => 1,
            'state' => PaymentStatus::CANCELED,
            'amount' => 1,
            'currency' => 'CZK',
        ])));
        $this->assertSame(PaymentState::CANCELLED, $gateway->getPayment('1')->state);

        $gateway = new GoPayGateway($this->config());
        $this->injectPayments($gateway, $this->fakePayments(status: $this->goPayResponse(200, [
            'id' => 2,
            'state' => PaymentStatus::TIMEOUTED,
            'amount' => 1,
            'currency' => 'CZK',
        ])));
        $this->assertSame(PaymentState::EXPIRED, $gateway->getPayment('2')->state);
    }

    #[Test]
    public function it_throws_a_gateway_exception_on_non_2xx_responses(): void
    {
        $gateway = new GoPayGateway($this->config());
        $this->injectPayments($gateway, $this->fakePayments(create: $this->goPayResponse(401, [
            'errors' => [['message' => 'Bad credentials']],
        ])));

        $this->expectException(GatewayException::class);
        $this->expectExceptionMessage('Bad credentials');

        $gateway->createPayment($this->paymentRequest());
    }

    #[Test]
    public function it_maps_refund_results_to_refund_state(): void
    {
        foreach ([
            'FINISHED' => RefundState::SUCCESS,
            'ACCEPTED' => RefundState::REQUESTED,
            'REJECTED' => RefundState::FAILED,
        ] as $native => $expected) {
            $gateway = new GoPayGateway($this->config());
            $this->injectPayments($gateway, $this->fakePayments(refund: $this->goPayResponse(200, [
                'id' => 'rf-1',
                'result' => $native,
            ])));

            $response = $gateway->refundPayment('p-1', new RefundRequest(amountInCents: 19900));

            $this->assertSame($expected, $response->state, "Native [{$native}] should map to expected state.");
            $this->assertSame('rf-1', $response->refundId);
        }
    }

    private function paymentRequest(): PaymentRequest
    {
        return new PaymentRequest(
            referenceId: 'ORDER-1',
            amountInCents: 19900,
            currency: Currency::CZK,
            description: 'Test',
            language: Language::CS,
            returnUrl: 'https://example.test/return',
            notificationUrl: 'https://example.test/notify',
            email: 'me@example.com',
            method: PaymentMethod::CARD,
        );
    }

    private function fakePayments(
        ?Response $create = null,
        ?Response $status = null,
        ?Response $refund = null,
    ): Payments {
        return new class($create, $status, $refund) extends Payments
        {
            public array $lastCreatePayload = [];

            public function __construct(
                public ?Response $createResponse,
                public ?Response $statusResponse,
                public ?Response $refundResponse,
            ) {}

            public function createPayment(array $rawPayment)
            {
                $this->lastCreatePayload = $rawPayment;

                return $this->createResponse;
            }

            public function getStatus($id)
            {
                return $this->statusResponse;
            }

            public function refundPayment($id, $data)
            {
                return $this->refundResponse;
            }
        };
    }

    private function goPayResponse(int $statusCode, array $body): Response
    {
        $response = new Response(json_encode($body));
        $response->statusCode = $statusCode;
        $response->json = $body;

        return $response;
    }

    private function injectPayments(GoPayGateway $gateway, Payments $fake): void
    {
        $reflection = new ReflectionProperty($gateway, 'payments');
        $reflection->setValue($gateway, $fake);
    }

    /** @return array<string, mixed> */
    private function config(): array
    {
        return [
            'environment' => 'sandbox',
            'credentials' => [
                'goid' => '123',
                'client_id' => 'cid',
                'client_secret' => 'sec',
            ],
            'options' => [
                'language' => 'CS',
                'timeout' => 30,
            ],
        ];
    }
}
