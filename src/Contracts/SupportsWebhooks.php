<?php

namespace Th3JK\Treasurer\Contracts;

use Illuminate\Http\Request;
use Th3JK\Treasurer\Webhooks\WebhookEvent;

interface SupportsWebhooks
{
    /**
     * Verify the inbound HTTP request was actually sent by this gateway.
     * Implementations may check header signatures (HMAC), body-embedded
     * shared secrets, or other gateway-specific markers.
     */
    public function verifySignature(Request $request): bool;

    /**
     * Parse the gateway payload into a typed WebhookEvent. Returns null
     * when the payload is structurally valid but not relevant (e.g., a
     * notification kind we don't react to).
     */
    public function parseEvent(Request $request): ?WebhookEvent;
}
