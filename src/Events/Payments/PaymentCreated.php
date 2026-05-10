<?php

namespace Th3JK\Treasurer\Events\Payments;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Th3JK\Treasurer\Models\Payment;

final class PaymentCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public Payment $payment) {}
}
