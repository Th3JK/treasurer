<?php

namespace Th3JK\Treasurer\Tests\Unit\Drivers;

use Comgate\SDK\Client;
use Comgate\SDK\Entity\Codes\PaymentStatusCode;
use Comgate\SDK\Entity\Payment;
use Comgate\SDK\Entity\Refund;
use Comgate\SDK\Entity\Response\PaymentCancelResponse;
use Comgate\SDK\Entity\Response\PaymentCreateResponse;
use Comgate\SDK\Entity\Response\PaymentStatusResponse;
use Comgate\SDK\Entity\Response\RefundResponse as ComgateRefundResponse;
use Comgate\SDK\Exception\ApiException;
use Comgate\SDK\Http\Response as ComgateHttpResponse;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Th3JK\Treasurer\Contracts\SupportsCancellation;
use Th3JK\Treasurer\Contracts\SupportsPayments;
use Th3JK\Treasurer\Contracts\SupportsRefunds;
use Th3JK\Treasurer\Drivers\Comgate\ComgateGateway;
use Th3JK\Treasurer\DTOs\PaymentRequest;
use Th3JK\Treasurer\DTOs\RefundRequest;
use Th3JK\Treasurer\Enums\Currency;
use Th3JK\Treasurer\Enums\Language;
use Th3JK\Treasurer\Enums\PaymentMethod;
use Th3JK\Treasurer\Enums\PaymentState;
use Th3JK\Treasurer\Enums\RefundState;
use Th3JK\Treasurer\Exceptions\GatewayException;
use Throwable;

class ComgateGatewayTest extends TestCase
{
    #[Test]
    public function it_implements_the_payment_capability_interfaces(): void
    {
        $gateway = new ComgateGateway($this->config());

        $this->assertInstanceOf(SupportsPayments::class, $gateway);
        $this->assertInstanceOf(SupportsRefunds::class, $gateway);
        $this->assertInstanceOf(SupportsCancellation::class, $gateway);
        $this->assertSame('comgate', $gateway->getName());
    }

    #[Test]
    public function it_creates_a_payment_and_returns_pending_state(): void
    {
        $gateway = new ComgateGateway($this->config());
        $this->injectClient($gateway, $this->fakeClient(create: new PaymentCreateResponse($this->httpResponse([
            'code' => 0,
            'message' => 'OK',
            'transId' => 'cg-trans-1',
            'redirect' => 'https://payments.comgate.cz/redirect/cg-trans-1',
        ]))));

        $response = $gateway->createPayment($this->paymentRequest());

        $this->assertSame('cg-trans-1', $response->paymentId);
        $this->assertSame(PaymentState::PENDING, $response->state);
        $this->assertSame(19900, $response->amountInCents);
        $this->assertSame(Currency::CZK, $response->currency);
        $this->assertSame('comgate', $response->provider);
        $this->assertSame('https://payments.comgate.cz/redirect/cg-trans-1', $response->redirectUrl);
    }

    #[Test]
    public function it_maps_native_status_codes_to_payment_state(): void
    {
        $cases = [
            PaymentStatusCode::PAID => PaymentState::PAID,
            PaymentStatusCode::PENDING => PaymentState::PENDING,
            PaymentStatusCode::CANCELLED => PaymentState::CANCELLED,
            PaymentStatusCode::AUTHORIZED => PaymentState::PENDING,
        ];

        foreach ($cases as $native => $expected) {
            $gateway = new ComgateGateway($this->config());
            $this->injectClient($gateway, $this->fakeClient(status: new PaymentStatusResponse($this->httpResponse([
                'code' => 0,
                'message' => 'OK',
                'transId' => 'cg-1',
                'price' => 19900,
                'curr' => 'CZK',
                'status' => $native,
            ]))));

            $this->assertSame($expected, $gateway->getPayment('cg-1')->state, "Native [{$native}]");
        }
    }

    #[Test]
    public function it_returns_requested_state_for_refunds(): void
    {
        $gateway = new ComgateGateway($this->config());
        $this->injectClient($gateway, $this->fakeClient(refund: new ComgateRefundResponse($this->httpResponse([
            'code' => 0,
            'message' => 'OK',
        ]))));

        $response = $gateway->refundPayment('cg-1', new RefundRequest(amountInCents: 5000));

        $this->assertSame(RefundState::REQUESTED, $response->state);
        $this->assertSame(5000, $response->amountInCents);
        $this->assertSame('comgate', $response->provider);
    }

    #[Test]
    public function it_wraps_api_exceptions_in_gateway_exceptions(): void
    {
        $gateway = new ComgateGateway($this->config());
        $this->injectClient($gateway, $this->fakeClient(createException: new ApiException('Bad request', 1500)));

        $this->expectException(GatewayException::class);
        $this->expectExceptionMessage('Comgate failed to create payment: Bad request');

        $gateway->createPayment($this->paymentRequest());
    }

    #[Test]
    public function it_returns_false_when_cancel_raises_an_api_exception(): void
    {
        $gateway = new ComgateGateway($this->config());
        $this->injectClient($gateway, $this->fakeClient(cancelException: new ApiException('Already cancelled', 1500)));

        $this->assertFalse($gateway->cancelPayment('cg-1'));
    }

    #[Test]
    public function it_returns_true_when_cancel_succeeds(): void
    {
        $gateway = new ComgateGateway($this->config());
        $this->injectClient($gateway, $this->fakeClient(cancel: new PaymentCancelResponse($this->httpResponse([
            'code' => 0,
            'message' => 'OK',
        ]))));

        $this->assertTrue($gateway->cancelPayment('cg-1'));
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
            method: PaymentMethod::CARD,
        );
    }

    private function fakeClient(
        ?PaymentCreateResponse $create = null,
        ?PaymentStatusResponse $status = null,
        ?ComgateRefundResponse $refund = null,
        ?PaymentCancelResponse $cancel = null,
        ?Throwable $createException = null,
        ?Throwable $cancelException = null,
    ): Client {
        return new class($create, $status, $refund, $cancel, $createException, $cancelException) extends Client
        {
            public function __construct(
                public ?PaymentCreateResponse $createResponse,
                public ?PaymentStatusResponse $statusResponse,
                public ?ComgateRefundResponse $refundResponse,
                public ?PaymentCancelResponse $cancelResponse,
                public ?Throwable $createException,
                public ?Throwable $cancelException,
            ) {}

            public function createPayment(Payment $payment): PaymentCreateResponse
            {
                if ($this->createException) {
                    throw $this->createException;
                }

                return $this->createResponse;
            }

            public function getStatus(string $transId): PaymentStatusResponse
            {
                return $this->statusResponse;
            }

            public function refundPayment(Refund $refund): ComgateRefundResponse
            {
                return $this->refundResponse;
            }

            public function cancelPayment(string $transId): PaymentCancelResponse
            {
                if ($this->cancelException) {
                    throw $this->cancelException;
                }

                return $this->cancelResponse;
            }
        };
    }

    private function httpResponse(array $body): ComgateHttpResponse
    {
        return new class(json_encode($body)) extends ComgateHttpResponse
        {
            public function __construct(public string $body) {}

            public function getContent(): string
            {
                return $this->body;
            }
        };
    }

    private function injectClient(ComgateGateway $gateway, Client $fake): void
    {
        $reflection = new ReflectionProperty($gateway, 'client');
        $reflection->setValue($gateway, $fake);
    }

    /** @return array<string, mixed> */
    private function config(): array
    {
        return [
            'environment' => 'sandbox',
            'credentials' => [
                'merchant' => 'merch-1',
                'secret' => 'secret-1',
            ],
            'options' => [
                'currency' => 'CZK',
            ],
        ];
    }
}
