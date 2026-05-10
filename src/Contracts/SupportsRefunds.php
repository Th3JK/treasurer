<?php

namespace Th3JK\Treasurer\Contracts;

use Th3JK\Treasurer\DTOs\RefundRequest;
use Th3JK\Treasurer\DTOs\RefundResponse;

interface SupportsRefunds
{
    /**
     * Issue a full or partial refund against a previously captured payment.
     * The returned RefundState may be SUCCESS (synchronous gateways) or
     * REQUESTED (asynchronous gateways — final state arrives via webhook).
     */
    public function refundPayment(string $paymentId, RefundRequest $request): RefundResponse;
}
