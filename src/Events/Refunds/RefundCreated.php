<?php

namespace Th3JK\Treasurer\Events\Refunds;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Th3JK\Treasurer\Models\Refund;

final class RefundCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public Refund $refund) {}
}
