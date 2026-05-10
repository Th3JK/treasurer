<?php

namespace Th3JK\Treasurer\Contracts;

interface SupportsCancellation
{
    /**
     * Cancel a payment that has not yet been captured. Semantics vary by
     * gateway — for some this is only valid against a preauthorized payment.
     */
    public function cancelPayment(string $paymentId): bool;
}
