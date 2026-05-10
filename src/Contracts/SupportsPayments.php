<?php

namespace Th3JK\Treasurer\Contracts;

use Th3JK\Treasurer\DTOs\PaymentRequest;
use Th3JK\Treasurer\DTOs\PaymentResponse;

interface SupportsPayments
{
    /**
     * Create a new payment at the gateway. The returned response carries the
     * provider-assigned payment id and (when applicable) a hosted-checkout
     * redirect URL that the host app should send the customer to.
     */
    public function createPayment(PaymentRequest $request): PaymentResponse;

    /**
     * Read the current state of a payment from the gateway. Used to
     * reconcile after the customer returns from the hosted checkout, or
     * to poll when webhook delivery is unreliable.
     */
    public function getPayment(string $paymentId): PaymentResponse;
}
