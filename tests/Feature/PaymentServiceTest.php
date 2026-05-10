<?php

namespace Th3JK\Treasurer\Tests\Feature;

use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Th3JK\Treasurer\Contracts\Gateway;
use Th3JK\Treasurer\DTOs\PaymentRequest;
use Th3JK\Treasurer\DTOs\PaymentResponse;
use Th3JK\Treasurer\Enums\Currency;
use Th3JK\Treasurer\Enums\Language;
use Th3JK\Treasurer\Enums\PaymentMethod;
use Th3JK\Treasurer\Enums\PaymentState;
use Th3JK\Treasurer\Enums\RefundState;
use Th3JK\Treasurer\Events\Payments\PaymentCreated;
use Th3JK\Treasurer\Events\Payments\PaymentPaid;
use Th3JK\Treasurer\Events\Payments\PaymentPending;
use Th3JK\Treasurer\Events\Refunds\RefundSucceeded;
use Th3JK\Treasurer\Models\Payment;
use Th3JK\Treasurer\Services\PaymentService;
use Th3JK\Treasurer\Tests\Feature\Fixtures\StubGateway;
use Th3JK\Treasurer\Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    private StubGateway $stub;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stub = new StubGateway;
        $this->app->instance(Gateway::class, $this->stub);
    }

    #[Test]
    public function it_persists_a_payment_and_dispatches_created_event(): void
    {
        Event::fake([PaymentCreated::class]);

        $this->stub->createResponse = new PaymentResponse(
            paymentId: 'gw-1',
            state: PaymentState::CREATED,
            amountInCents: 19900,
            currency: Currency::CZK,
            provider: 'stub',
            redirectUrl: 'https://example.test/redirect',
            method: PaymentMethod::CARD,
            raw: ['id' => 'gw-1', 'state' => 'CREATED'],
        );

        $service = $this->app->make(PaymentService::class);
        $payment = $service->create($this->paymentRequest());

        $this->assertSame('stub', $payment->provider);
        $this->assertSame('gw-1', $payment->provider_payment_id);
        $this->assertSame(PaymentState::CREATED, $payment->status);
        $this->assertSame(PaymentMethod::CARD, $payment->method);
        $this->assertSame('199.00', $payment->amount);
        $this->assertSame('https://example.test/redirect', $payment->redirect_url);
        $this->assertSame(['order_id' => 1001], $payment->metadata);

        $this->assertDatabaseHas('payments', [
            'provider' => 'stub',
            'provider_payment_id' => 'gw-1',
            'status' => 'created',
        ]);
        $this->assertDatabaseHas('payment_events', [
            'payment_id' => $payment->id,
            'type' => 'created',
        ]);

        Event::assertDispatched(PaymentCreated::class, fn (PaymentCreated $e) => $e->payment->id === $payment->id);
    }

    #[Test]
    public function it_does_not_dispatch_state_change_event_when_status_is_unchanged(): void
    {
        Event::fake();

        $this->stub->createResponse = $this->responseInState(PaymentState::PENDING);
        $service = $this->app->make(PaymentService::class);
        $payment = $service->create($this->paymentRequest());

        Event::assertDispatched(PaymentCreated::class);
        Event::fake();

        $this->stub->getResponse = $this->responseInState(PaymentState::PENDING);
        $refreshed = $service->refresh($payment);

        $this->assertSame(PaymentState::PENDING, $refreshed->status);
        Event::assertNotDispatched(PaymentPaid::class);
        Event::assertNotDispatched(PaymentPending::class);
    }

    #[Test]
    public function it_dispatches_paid_event_when_state_transitions_to_paid(): void
    {
        Event::fake([PaymentCreated::class, PaymentPaid::class]);

        $this->stub->createResponse = $this->responseInState(PaymentState::PENDING);
        $service = $this->app->make(PaymentService::class);
        $payment = $service->create($this->paymentRequest());

        $this->stub->getResponse = $this->responseInState(PaymentState::PAID);
        $refreshed = $service->refresh($payment);

        $this->assertSame(PaymentState::PAID, $refreshed->status);
        Event::assertDispatched(PaymentPaid::class, fn (PaymentPaid $e) => $e->payment->id === $payment->id);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'paid',
        ]);
    }

    #[Test]
    public function it_finalises_pending_gopay_refunds_when_status_reports_refunded(): void
    {
        $payment = Payment::create([
            'provider' => 'gopay',
            'provider_payment_id' => 'gp-77',
            'amount' => 199.00,
            'currency' => 'CZK',
            'status' => PaymentState::PAID,
            'method' => null,
            'metadata' => [],
        ]);

        $refund = $payment->refunds()->create([
            'provider' => 'gopay',
            'provider_refund_id' => 'rf-77',
            'amount' => 199.00,
            'currency' => 'CZK',
            'status' => RefundState::REQUESTED,
            'reason' => null,
        ]);

        Event::fake([RefundSucceeded::class]);

        $this->stub->getResponse = new PaymentResponse(
            paymentId: 'gp-77',
            state: PaymentState::PAID,
            amountInCents: 19900,
            currency: Currency::CZK,
            provider: 'gopay',
            raw: ['id' => 'gp-77', 'state' => 'REFUNDED'],
        );

        $this->app->make(PaymentService::class)->refresh($payment);

        $this->assertSame(RefundState::SUCCESS, $refund->fresh()->status);
        Event::assertDispatched(
            RefundSucceeded::class,
            fn (RefundSucceeded $e) => $e->refund->id === $refund->id,
        );
    }

    #[Test]
    public function it_does_not_finalise_refunds_for_non_gopay_providers(): void
    {
        $payment = Payment::create([
            'provider' => 'comgate',
            'provider_payment_id' => 'cg-77',
            'amount' => 199.00,
            'currency' => 'CZK',
            'status' => PaymentState::PAID,
            'method' => null,
            'metadata' => [],
        ]);

        $refund = $payment->refunds()->create([
            'provider' => 'comgate',
            'provider_refund_id' => null,
            'amount' => 199.00,
            'currency' => 'CZK',
            'status' => RefundState::REQUESTED,
            'reason' => null,
        ]);

        Event::fake([RefundSucceeded::class]);

        $this->stub->getResponse = new PaymentResponse(
            paymentId: 'cg-77',
            state: PaymentState::PAID,
            amountInCents: 19900,
            currency: Currency::CZK,
            provider: 'comgate',
            raw: ['state' => 'REFUNDED'],
        );

        $this->app->make(PaymentService::class)->refresh($payment);

        $this->assertSame(RefundState::REQUESTED, $refund->fresh()->status);
        Event::assertNotDispatched(RefundSucceeded::class);
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
            metadata: ['order_id' => 1001],
        );
    }

    private function responseInState(PaymentState $state): PaymentResponse
    {
        return new PaymentResponse(
            paymentId: 'gw-1',
            state: $state,
            amountInCents: 19900,
            currency: Currency::CZK,
            provider: 'stub',
            redirectUrl: $state === PaymentState::CREATED ? 'https://example.test/redirect' : null,
            method: PaymentMethod::CARD,
        );
    }

    private function makePayment(): Payment
    {
        $this->stub->createResponse = $this->responseInState(PaymentState::CREATED);

        return $this->app->make(PaymentService::class)->create($this->paymentRequest());
    }
}
