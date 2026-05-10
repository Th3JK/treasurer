<?php

namespace Th3JK\Treasurer\Contracts;

interface SupportsCancellation
{
    /**
     * Cancel a payment that has not yet been captured. Semantics vary by
     * gateway — for some this is only valid against a preauthorized payment
     * (e.g. GoPay's `voidAuthorization`, which returns an error on plain
     * CREATED/PENDING payments). When the gateway rejects, implementations
     * should return `false` instead of throwing.
     */
    public function cancelPayment(string $paymentId): bool;
}
