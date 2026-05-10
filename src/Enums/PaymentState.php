<?php

namespace Th3JK\Treasurer\Enums;

/**
 * Lifecycle states for a payment.
 *
 * Final statuses (PAID, CANCELLED, REFUNDED, FAILED, TIMED_OUT) accept no further
 * gateway events. PARTIALLY_REFUNDED is intentionally not final — additional refunds
 * may follow until the full amount is refunded, at which point the status becomes REFUNDED.
 */
enum PaymentState: string
{
    case CREATED = 'created';
    case PENDING = 'pending';
    case PAID = 'paid';
    case CANCELLED = 'cancelled';
    case FAILED = 'failed';
    case EXPIRED = 'expired';

    /**
     * Returns true for statuses that accept no further gateway events (PAID, CANCELLED,
     * REFUNDED, FAILED, TIMED_OUT). PARTIALLY_REFUNDED is excluded because more refunds
     * may still follow.
     */
    public function isFinal(): bool
    {
        return match ($this) {
            self::PAID, self::CANCELLED, self::FAILED, self::EXPIRED => true,
            default => false,
        };
    }
}
