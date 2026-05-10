<?php

namespace Th3JK\Treasurer\Services;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;
use Th3JK\Treasurer\Contracts\Gateway;
use Th3JK\Treasurer\Contracts\SupportsRefunds;
use Th3JK\Treasurer\Drivers\GoPay\GoPayGateway;
use Th3JK\Treasurer\DTOs\PaymentResponse;
use Th3JK\Treasurer\DTOs\RefundRequest;
use Th3JK\Treasurer\Enums\RefundState;
use Th3JK\Treasurer\Events\Refunds\RefundCreated;
use Th3JK\Treasurer\Events\Refunds\RefundFailed;
use Th3JK\Treasurer\Events\Refunds\RefundSucceeded;
use Th3JK\Treasurer\Exceptions\UnsupportedFeatureException;
use Th3JK\Treasurer\Models\Payment;
use Th3JK\Treasurer\Models\Refund;

class RefundService
{
    public function __construct(
        private Gateway $gateway,
        private PaymentEventService $events,
        private Dispatcher $dispatcher,
        private ConnectionInterface $connection,
    ) {}

    public function create(Payment $payment, RefundRequest $request): Refund
    {
        $gateway = $this->ensureRefunds();

        if ($payment->provider !== $gateway->getName()) {
            throw UnsupportedFeatureException::forCapability(
                $gateway->getName(),
                "refund of payment created via [{$payment->provider}]",
            );
        }

        $response = $gateway->refundPayment($payment->provider_payment_id, $request);

        $refund = $this->connection->transaction(function () use ($payment, $response, $request) {
            $refund = $payment->refunds()->create([
                'provider' => $response->provider,
                'provider_refund_id' => $response->refundId,
                'amount' => $response->amountInCents / 100,
                'currency' => $payment->currency,
                'status' => $response->state,
                'reason' => $request->reason,
            ]);

            $this->events->record($payment, 'refund.requested', $response->raw);

            return $refund;
        });

        $this->dispatcher->dispatch(new RefundCreated($refund));

        if ($response->state === RefundState::SUCCESS) {
            $this->dispatcher->dispatch(new RefundSucceeded($refund));
        } elseif ($response->state === RefundState::FAILED) {
            $this->dispatcher->dispatch(new RefundFailed($refund));
        }

        return $refund;
    }

    /**
     * Reconcile pending refunds for a payment based on a freshly fetched
     * PaymentResponse. Currently this only finalises refunds for GoPay,
     * which surfaces refund completion through the parent payment's
     * native state (REFUNDED / PARTIALLY_REFUNDED). Other providers are
     * a no-op until they grow their own refund-finalization signal.
     */
    public function reconcileFor(Payment $payment, PaymentResponse $response): void
    {
        if ($payment->provider !== GoPayGateway::NAME) {
            return;
        }

        $native = (string) ($response->raw['state'] ?? '');
        if (! in_array($native, ['REFUNDED', 'PARTIALLY_REFUNDED'], true)) {
            return;
        }

        $pending = $payment->refunds()
            ->where('status', RefundState::REQUESTED)
            ->get();

        if ($pending->isEmpty()) {
            return;
        }

        $this->connection->transaction(function () use ($payment, $pending, $response) {
            foreach ($pending as $refund) {
                $refund->update(['status' => RefundState::SUCCESS]);
                $this->events->record($payment, 'refund.succeeded', $response->raw);
            }
        });

        foreach ($pending as $refund) {
            $this->dispatcher->dispatch(new RefundSucceeded($refund->refresh()));
        }
    }

    private function ensureRefunds(): Gateway&SupportsRefunds
    {
        if (! $this->gateway instanceof SupportsRefunds) {
            throw UnsupportedFeatureException::forCapability($this->gateway->getName(), 'refunds');
        }

        return $this->gateway;
    }
}
