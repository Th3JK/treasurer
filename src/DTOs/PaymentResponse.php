<?php

namespace Th3JK\Treasurer\DTOs;

use Th3JK\Treasurer\Enums\Currency;
use Th3JK\Treasurer\Enums\PaymentMethod;
use Th3JK\Treasurer\Enums\PaymentState;

final readonly class PaymentResponse
{
    public function __construct(
        public string $paymentId,
        public PaymentState $state,
        public int $amountInCents,
        public Currency $currency,
        public string $provider,
        public ?string $redirectUrl = null,
        public ?PaymentMethod $method = null,
        public array $raw = [],
    ) {}
}
