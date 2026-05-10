<?php

namespace Th3JK\Treasurer\Tests\Feature;

use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Th3JK\Treasurer\Contracts\Gateway;
use Th3JK\Treasurer\DTOs\PaymentRequest;
use Th3JK\Treasurer\DTOs\PaymentResponse;
use Th3JK\Treasurer\DTOs\RefundRequest;
use Th3JK\Treasurer\DTOs\RefundResponse;
use Th3JK\Treasurer\Enums\Currency;
use Th3JK\Treasurer\Enums\Language;
use Th3JK\Treasurer\Enums\PaymentMethod;
use Th3JK\Treasurer\Enums\PaymentState;
use Th3JK\Treasurer\Enums\RefundState;
use Th3JK\Treasurer\Events\Refunds\RefundCreated;
use Th3JK\Treasurer\Events\Refunds\RefundFailed;
use Th3JK\Treasurer\Events\Refunds\RefundSucceeded;
use Th3JK\Treasurer\Exceptions\UnsupportedFeatureException;
use Th3JK\Treasurer\Models\Payment;
use Th3JK\Treasurer\Services\PaymentService;
use Th3JK\Treasurer\Services\RefundService;
use Th3JK\Treasurer\Tests\Feature\Fixtures\StubGateway;
use Th3JK\Treasurer\Tests\TestCase;

class RefundServiceTest extends TestCase
{
    private StubGateway $stub;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stub = new StubGateway;
        $this->app->instance(Gateway::class, $this->stub);
    }

    #[Test]
    public function it_persists_a_refund_and_dispatches_created_event(): void
    {
        $payment = $this->makePayment();
        Event::fake([RefundCreated::class, RefundSucceeded::class, RefundFailed::class]);

        $this->stub->refundResponse = new RefundResponse(
            state: RefundState::REQUESTED,
            amountInCents: 19900,
            provider: 'stub',
            refundId: 'rf-1',
        );

        $refund = $this->app->make(RefundService::class)->create(
            $payment,
            new RefundRequest(amountInCents: 19900, reason: 'Customer cancelled'),
        );

        $this->assertSame('stub', $refund->provider);
        $this->assertSame('rf-1', $refund->provider_refund_id);
        $this->assertSame(RefundState::REQUESTED, $refund->status);
        $this->assertSame('199.00', $refund->amount);
        $this->assertSame('Customer cancelled', $refund->reason);
        $this->assertSame($payment->id, $refund->payment_id);

        Event::assertDispatched(RefundCreated::class);
        Event::assertNotDispatched(RefundSucceeded::class);
        Event::assertNotDispatched(RefundFailed::class);
    }

    #[Test]
    public function it_dispatches_succeeded_event_for_synchronous_success(): void
    {
        $payment = $this->makePayment();
        Event::fake([RefundCreated::class, RefundSucceeded::class]);

        $this->stub->refundResponse = new RefundResponse(
            state: RefundState::SUCCESS,
            amountInCents: 5000,
            provider: 'stub',
            refundId: 'rf-2',
        );

        $refund = $this->app->make(RefundService::class)->create(
            $payment,
            new RefundRequest(amountInCents: 5000),
        );

        Event::assertDispatched(RefundCreated::class);
        Event::assertDispatched(
            RefundSucceeded::class,
            fn (RefundSucceeded $e) => $e->refund->id === $refund->id,
        );
    }

    #[Test]
    public function it_dispatches_failed_event_when_gateway_returns_failure(): void
    {
        $payment = $this->makePayment();
        Event::fake([RefundFailed::class]);

        $this->stub->refundResponse = new RefundResponse(
            state: RefundState::FAILED,
            amountInCents: 1,
            provider: 'stub',
            refundId: null,
        );

        $this->app->make(RefundService::class)->create(
            $payment,
            new RefundRequest(amountInCents: 1),
        );

        Event::assertDispatched(RefundFailed::class);
    }

    #[Test]
    public function it_refuses_to_refund_a_payment_from_a_different_provider(): void
    {
        $payment = $this->makePayment();
        $payment->update(['provider' => 'gopay']);

        $this->expectException(UnsupportedFeatureException::class);

        $this->app->make(RefundService::class)->create(
            $payment,
            new RefundRequest(amountInCents: 100),
        );
    }

    private function makePayment(): Payment
    {
        $this->stub->createResponse = new PaymentResponse(
            paymentId: 'gw-1',
            state: PaymentState::PAID,
            amountInCents: 19900,
            currency: Currency::CZK,
            provider: 'stub',
            method: PaymentMethod::CARD,
        );

        return $this->app->make(PaymentService::class)->create(new PaymentRequest(
            referenceId: 'ORDER-1',
            amountInCents: 19900,
            currency: Currency::CZK,
            description: 'Test',
            language: Language::CS,
            returnUrl: 'https://example.test/return',
            notificationUrl: 'https://example.test/notify',
            method: PaymentMethod::CARD,
        ));
    }
}
