<?php

namespace Th3JK\Treasurer\Services;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;
use Th3JK\Treasurer\Contracts\Gateway;
use Th3JK\Treasurer\Contracts\SupportsPayments;
use Th3JK\Treasurer\DTOs\PaymentRequest;
use Th3JK\Treasurer\Enums\PaymentState;
use Th3JK\Treasurer\Events\Payments\PaymentCancelled;
use Th3JK\Treasurer\Events\Payments\PaymentCreated;
use Th3JK\Treasurer\Events\Payments\PaymentExpired;
use Th3JK\Treasurer\Events\Payments\PaymentFailed;
use Th3JK\Treasurer\Events\Payments\PaymentPaid;
use Th3JK\Treasurer\Events\Payments\PaymentPending;
use Th3JK\Treasurer\Exceptions\UnsupportedFeatureException;
use Th3JK\Treasurer\Models\Payment;

class PaymentService
{
    public function __construct(
        private Gateway $gateway,
        private PaymentEventService $events,
        private Dispatcher $dispatcher,
        private ConnectionInterface $connection,
        private RefundService $refunds,
    ) {}

    public function create(PaymentRequest $request): Payment
    {
        $gateway = $this->ensurePayments();
        $response = $gateway->createPayment($request);

        $payment = $this->connection->transaction(function () use ($response, $request) {
            $payment = Payment::create([
                'provider' => $response->provider,
                'provider_payment_id' => $response->paymentId,
                'amount' => $response->amountInCents / 100,
                'currency' => $response->currency->value,
                'status' => $response->state,
                'method' => $response->method?->value ?? $request->method?->value,
                'metadata' => $request->metadata,
            ]);

            $this->events->record($payment, 'created', $response->raw);

            return $payment;
        });

        $payment->redirect_url = $response->redirectUrl;

        $this->dispatcher->dispatch(new PaymentCreated($payment));

        return $payment;
    }

    public function refresh(Payment $payment): Payment
    {
        $gateway = $this->ensurePayments();
        $response = $gateway->getPayment($payment->provider_payment_id);

        // Refund finalisation can fire even when the mapped payment state
        // does not visibly change (e.g. GoPay PAID → PARTIALLY_REFUNDED both
        // map to PaymentState::PAID).
        $this->refunds->reconcileFor($payment, $response);

        $previousState = $payment->status;

        if ($previousState === $response->state) {
            $this->events->record($payment, 'status.refreshed', $response->raw);

            return $payment;
        }

        $this->connection->transaction(function () use ($payment, $response) {
            $payment->update(['status' => $response->state]);
            $this->events->record($payment, 'status.changed', $response->raw);
        });

        $this->dispatchStateEvent($payment, $response->state);

        return $payment->refresh();
    }

    private function ensurePayments(): SupportsPayments
    {
        if (! $this->gateway instanceof SupportsPayments) {
            throw UnsupportedFeatureException::forCapability($this->gateway->getName(), 'payments');
        }

        return $this->gateway;
    }

    private function dispatchStateEvent(Payment $payment, PaymentState $state): void
    {
        $event = match ($state) {
            PaymentState::PAID => new PaymentPaid($payment),
            PaymentState::PENDING => new PaymentPending($payment),
            PaymentState::CANCELLED => new PaymentCancelled($payment),
            PaymentState::FAILED => new PaymentFailed($payment),
            PaymentState::EXPIRED => new PaymentExpired($payment),
            PaymentState::CREATED => null,
        };

        if ($event !== null) {
            $this->dispatcher->dispatch($event);
        }
    }
}
