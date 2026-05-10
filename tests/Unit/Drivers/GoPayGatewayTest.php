<?php

namespace Th3JK\Treasurer\Tests\Unit\Drivers;

use GoPay\Definition\Response\PaymentStatus;
use GoPay\Http\Response;
use GoPay\Payments;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Th3JK\Treasurer\Contracts\SupportsCancellation;
use Th3JK\Treasurer\Contracts\SupportsPayments;
use Th3JK\Treasurer\Contracts\SupportsRefunds;
use Th3JK\Treasurer\Drivers\GoPay\GoPayGateway;
use Th3JK\Treasurer\DTOs\PaymentRequest;
use Th3JK\Treasurer\DTOs\RefundRequest;
use Th3JK\Treasurer\Enums\Currency;
use Th3JK\Treasurer\Enums\Language;
use Th3JK\Treasurer\Enums\PaymentMethod;
use Th3JK\Treasurer\Enums\PaymentState;
use Th3JK\Treasurer\Enums\RefundState;
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
        $this->assertSame('gopay', $gateway->getName());
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
            public function __construct(
                public ?Response $createResponse,
                public ?Response $statusResponse,
                public ?Response $refundResponse,
            ) {}

            public function createPayment(array $rawPayment)
            {
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
