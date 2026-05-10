<?php

namespace Th3JK\Treasurer\DTOs;

use Th3JK\Treasurer\Enums\Currency;
use Th3JK\Treasurer\Enums\Language;
use Th3JK\Treasurer\Enums\PaymentMethod;

final readonly class PaymentRequest
{
    public function __construct(
        public string $referenceId,
        public int $amountInCents,
        public Currency $currency,
        public string $description,
        public Language $language,
        public string $returnUrl,
        public string $notificationUrl,
        public ?string $email = null,
        public ?PaymentMethod $method = null,
        public array $metadata = [],
    ) {}
}
