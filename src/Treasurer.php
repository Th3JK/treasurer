<?php

namespace Th3JK\Treasurer;

use Th3JK\Treasurer\Contracts\Gateway;
use Th3JK\Treasurer\DTOs\PaymentRequest;
use Th3JK\Treasurer\DTOs\RefundRequest;
use Th3JK\Treasurer\Models\Payment;
use Th3JK\Treasurer\Models\Refund;
use Th3JK\Treasurer\Services\PaymentService;
use Th3JK\Treasurer\Services\RefundService;

final class Treasurer
{
    public function __construct(
        private Gateway $gateway,
        private PaymentService $payments,
        private RefundService $refunds,
    ) {}

    public function gateway(): Gateway
    {
        return $this->gateway;
    }

    public function pay(PaymentRequest $request): Payment
    {
        return $this->payments->create($request);
    }

    public function status(Payment $payment): Payment
    {
        return $this->payments->refresh($payment);
    }

    public function refund(Payment $payment, RefundRequest $request): Refund
    {
        return $this->refunds->create($payment, $request);
    }
}
