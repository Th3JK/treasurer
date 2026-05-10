<?php

namespace Th3JK\Treasurer\Facades;

use Illuminate\Support\Facades\Facade;
use Th3JK\Treasurer\Contracts\Gateway;
use Th3JK\Treasurer\DTOs\PaymentRequest;
use Th3JK\Treasurer\DTOs\RefundRequest;
use Th3JK\Treasurer\Models\Payment as PaymentModel;
use Th3JK\Treasurer\Models\Refund as RefundModel;

/**
 * @method static Gateway gateway()
 * @method static PaymentModel pay(PaymentRequest $request)
 * @method static PaymentModel status(PaymentModel $payment)
 * @method static RefundModel refund(PaymentModel $payment, RefundRequest $request)
 *
 * @see \Th3JK\Treasurer\Treasurer
 */
class Treasurer extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'treasurer';
    }
}
