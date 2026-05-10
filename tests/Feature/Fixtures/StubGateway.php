<?php

namespace Th3JK\Treasurer\Tests\Feature\Fixtures;

use Th3JK\Treasurer\Contracts\Gateway;
use Th3JK\Treasurer\Contracts\SupportsCancellation;
use Th3JK\Treasurer\Contracts\SupportsPayments;
use Th3JK\Treasurer\Contracts\SupportsRefunds;
use Th3JK\Treasurer\DTOs\PaymentRequest;
use Th3JK\Treasurer\DTOs\PaymentResponse;
use Th3JK\Treasurer\DTOs\RefundRequest;
use Th3JK\Treasurer\DTOs\RefundResponse;

class StubGateway implements Gateway, SupportsCancellation, SupportsPayments, SupportsRefunds
{
    public ?PaymentResponse $createResponse = null;

    public ?PaymentResponse $getResponse = null;

    public ?RefundResponse $refundResponse = null;

    public ?PaymentRequest $lastCreateRequest = null;

    public ?string $lastGetId = null;

    public ?string $lastRefundId = null;

    public ?RefundRequest $lastRefundRequest = null;

    public bool $cancelResult = true;

    public function getName(): string
    {
        return 'stub';
    }

    public function createPayment(PaymentRequest $request): PaymentResponse
    {
        $this->lastCreateRequest = $request;

        return $this->createResponse;
    }

    public function getPayment(string $paymentId): PaymentResponse
    {
        $this->lastGetId = $paymentId;

        return $this->getResponse;
    }

    public function refundPayment(string $paymentId, RefundRequest $request): RefundResponse
    {
        $this->lastRefundId = $paymentId;
        $this->lastRefundRequest = $request;

        return $this->refundResponse;
    }

    public function cancelPayment(string $paymentId): bool
    {
        return $this->cancelResult;
    }
}
