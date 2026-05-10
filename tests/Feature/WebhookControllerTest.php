<?php

namespace Th3JK\Treasurer\Tests\Feature;

use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Th3JK\Treasurer\Contracts\Gateway;
use Th3JK\Treasurer\DTOs\PaymentResponse;
use Th3JK\Treasurer\Enums\Currency;
use Th3JK\Treasurer\Enums\PaymentState;
use Th3JK\Treasurer\Enums\WebhookEventKind;
use Th3JK\Treasurer\Events\Payments\PaymentPaid;
use Th3JK\Treasurer\Models\Payment;
use Th3JK\Treasurer\Support\GatewayRegistry;
use Th3JK\Treasurer\Tests\Feature\Fixtures\StubGateway;
use Th3JK\Treasurer\Tests\TestCase;
use Th3JK\Treasurer\Webhooks\WebhookEvent;

class WebhookControllerTest extends TestCase
{
    private StubGateway $stub;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stub = new StubGateway;
        $this->app->instance(Gateway::class, $this->stub);

        $this->app->make(GatewayRegistry::class)->extend('stub', fn (array $config) => $this->stub);

        config()->set('treasurer.providers.stub', [
            'webhooks' => ['token' => 'expected-token'],
        ]);
    }

    #[Test]
    public function it_returns_404_when_url_token_does_not_match_config(): void
    {
        $this->postJson('/treasurer/webhooks/stub/wrong-token')->assertNotFound();
    }

    #[Test]
    public function it_returns_404_when_provider_has_no_token_configured(): void
    {
        config()->set('treasurer.providers.stub.webhooks.token', '');

        $this->postJson('/treasurer/webhooks/stub/expected-token')->assertNotFound();
    }

    #[Test]
    public function it_returns_401_when_driver_rejects_the_signature(): void
    {
        $this->stub->verifySignatureResult = false;

        $this->postJson('/treasurer/webhooks/stub/expected-token')->assertUnauthorized();
    }

    #[Test]
    public function it_returns_no_content_when_parser_returns_null(): void
    {
        $this->stub->webhookEvent = null;

        $this->postJson('/treasurer/webhooks/stub/expected-token')->assertNoContent();
    }

    #[Test]
    public function it_returns_404_when_payment_id_does_not_match_any_row(): void
    {
        $this->stub->webhookEvent = new WebhookEvent(
            kind: WebhookEventKind::PAYMENT_NOTIFICATION,
            paymentId: 'does-not-exist',
        );

        $this->postJson('/treasurer/webhooks/stub/expected-token')->assertNotFound();
    }

    #[Test]
    public function it_refreshes_the_payment_and_dispatches_paid_event_on_state_transition(): void
    {
        Event::fake([PaymentPaid::class]);

        $payment = Payment::create([
            'provider' => 'stub',
            'provider_payment_id' => 'gw-77',
            'amount' => 199.00,
            'currency' => 'CZK',
            'status' => PaymentState::PENDING,
            'method' => null,
            'metadata' => [],
        ]);

        $this->stub->webhookEvent = new WebhookEvent(
            kind: WebhookEventKind::PAYMENT_NOTIFICATION,
            paymentId: 'gw-77',
        );
        $this->stub->getResponse = new PaymentResponse(
            paymentId: 'gw-77',
            state: PaymentState::PAID,
            amountInCents: 19900,
            currency: Currency::CZK,
            provider: 'stub',
        );

        $this->postJson('/treasurer/webhooks/stub/expected-token')->assertNoContent();

        $this->assertSame(PaymentState::PAID, $payment->fresh()->status);
        Event::assertDispatched(PaymentPaid::class);
    }

    #[Test]
    public function it_returns_501_for_a_driver_that_does_not_support_webhooks(): void
    {
        $nonWebhookDriver = new class implements Gateway
        {
            public function getName(): string
            {
                return 'nowebhook';
            }
        };

        $this->app->make(GatewayRegistry::class)
            ->extend('nowebhook', fn () => $nonWebhookDriver);

        config()->set('treasurer.providers.nowebhook', [
            'webhooks' => ['token' => 'expected-token'],
        ]);

        $this->postJson('/treasurer/webhooks/nowebhook/expected-token')
            ->assertStatus(501);
    }
}
