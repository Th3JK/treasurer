<?php

namespace Th3JK\Treasurer\Enums;

enum WebhookEventKind: string
{
    case PAYMENT_NOTIFICATION = 'payment.notification';
}
