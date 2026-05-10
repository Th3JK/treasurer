<?php

namespace Th3JK\Treasurer\DTOs;

use Th3JK\Treasurer\Enums\RefundState;

final readonly class RefundResponse
{
    public function __construct(
        public RefundState $state,
        public int $amountInCents,
        public string $provider,
        public ?string $refundId = null,
        public array $raw = [],
    ) {}
}
