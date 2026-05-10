<?php

namespace Th3JK\Treasurer\DTOs;

final readonly class RefundRequest
{
    public function __construct(
        public int $amountInCents,
        public ?string $referenceId = null,
        public ?string $reason = null,
        public array $metadata = [],
    ) {}
}
