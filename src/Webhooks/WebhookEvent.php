<?php

namespace Th3JK\Treasurer\Webhooks;

use Th3JK\Treasurer\Enums\PaymentState;
use Th3JK\Treasurer\Enums\WebhookEventKind;

final readonly class WebhookEvent
{
    public function __construct(
        public WebhookEventKind $kind,
        public string $paymentId,
        public ?PaymentState $paymentState = null,
        public array $raw = [],
    ) {}
}
