<?php

namespace Th3JK\Treasurer\Services;

use Th3JK\Treasurer\Models\Payment;
use Th3JK\Treasurer\Models\PaymentEvent;

class PaymentEventService
{
    /** @param array<string, mixed> $payload */
    public function record(Payment $payment, string $type, array $payload): PaymentEvent
    {
        return $payment->events()->create([
            'provider' => $payment->provider,
            'type' => $type,
            'payload' => $payload,
        ]);
    }
}
