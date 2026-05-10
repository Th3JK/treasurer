<?php

namespace Th3JK\Treasurer\Enums;

use Comgate\SDK\Entity\Codes\CurrencyCode;
use GoPay\Definition\Payment\Currency as GoPayCurrency;
use Th3JK\Treasurer\Exceptions\UnsupportedFeatureException;

enum Currency: string
{
    case CZK = 'CZK';
    case EUR = 'EUR';
    case PLN = 'PLN';
    case HUF = 'HUF';
    case USD = 'USD';
    case GBP = 'GBP';
    case RON = 'RON';
    case BGN = 'BGN';
    case HRK = 'HRK';
    case NOK = 'NOK';
    case SEK = 'SEK';

    public function gopay(): string
    {
        return match ($this) {
            self::CZK => GoPayCurrency::CZECH_CROWNS,
            self::EUR => GoPayCurrency::EUROS,
            self::PLN => GoPayCurrency::POLISH_ZLOTY,
            self::HUF => GoPayCurrency::HUNGARIAN_FORINT,
            self::USD => GoPayCurrency::US_DOLLAR,
            self::GBP => GoPayCurrency::BRITISH_POUND,
            self::RON => GoPayCurrency::ROMANIAN_LEU,
            self::BGN => GoPayCurrency::BULGARIAN_LEV,
            default => throw UnsupportedFeatureException::forValue('gopay', $this->value, 'currency'),
        };
    }

    public function comgate(): string
    {
        return match ($this) {
            self::CZK => CurrencyCode::CZK,
            self::EUR => CurrencyCode::EUR,
            self::PLN => CurrencyCode::PLN,
            self::HUF => CurrencyCode::HUF,
            self::USD => CurrencyCode::USD,
            self::GBP => CurrencyCode::GBP,
            self::RON => CurrencyCode::RON,
            self::HRK => CurrencyCode::HRK,
            self::NOK => CurrencyCode::NOK,
            self::SEK => CurrencyCode::SEK,
            default => throw UnsupportedFeatureException::forValue('comgate', $this->value, 'currency'),
        };
    }
}
