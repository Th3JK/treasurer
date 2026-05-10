<?php

namespace Th3JK\Treasurer\Enums;

use Comgate\SDK\Entity\Codes\PaymentMethodCode;
use GoPay\Definition\Payment\PaymentInstrument;
use Th3JK\Treasurer\Exceptions\UnsupportedFeatureException;

enum PaymentMethod: string
{
    case CARD = 'CARD';
    case BANK = 'BANK';
    case APPLE_PAY = 'APPLE_PAY';
    case GOOGLE_PAY = 'GOOGLE_PAY';
    case PAYPAL = 'PAYPAL';
    case BITCOIN = 'BITCOIN';
    case LATER = 'LATER';

    public function gopay(): string
    {
        return match ($this) {
            self::CARD => PaymentInstrument::PAYMENT_CARD,
            self::BANK => PaymentInstrument::BANK_ACCOUNT,
            self::APPLE_PAY => PaymentInstrument::APPLE_PAY,
            self::GOOGLE_PAY => PaymentInstrument::GPAY,
            self::PAYPAL => PaymentInstrument::PAYPAL,
            self::BITCOIN => PaymentInstrument::BITCOIN,
            self::LATER => throw UnsupportedFeatureException::forValue('gopay', $this->value, 'payment method'),
        };
    }

    public function comgate(): string
    {
        return match ($this) {
            self::CARD => PaymentMethodCode::ALL_CARDS,
            self::BANK => PaymentMethodCode::ALL_BANKS,
            self::APPLE_PAY => PaymentMethodCode::APPLEPAY_REDIRECT,
            self::GOOGLE_PAY => PaymentMethodCode::GOOGLEPAY_REDIRECT,
            self::PAYPAL => PaymentMethodCode::PAYPAL,
            self::LATER => PaymentMethodCode::LATER_ALL,
            self::BITCOIN => throw UnsupportedFeatureException::forValue('comgate', $this->value, 'payment method'),
        };
    }
}
