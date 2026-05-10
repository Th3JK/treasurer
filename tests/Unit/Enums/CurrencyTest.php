<?php

namespace Th3JK\Treasurer\Tests\Unit\Enums;

use Comgate\SDK\Entity\Codes\CurrencyCode;
use GoPay\Definition\Payment\Currency as GoPayCurrency;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Th3JK\Treasurer\Enums\Currency;
use Th3JK\Treasurer\Exceptions\UnsupportedFeatureException;

class CurrencyTest extends TestCase
{
    #[Test]
    public function it_translates_shared_currencies_to_gopay_constants(): void
    {
        $this->assertSame(GoPayCurrency::CZECH_CROWNS, Currency::CZK->gopay());
        $this->assertSame(GoPayCurrency::EUROS, Currency::EUR->gopay());
        $this->assertSame(GoPayCurrency::US_DOLLAR, Currency::USD->gopay());
        $this->assertSame(GoPayCurrency::BULGARIAN_LEV, Currency::BGN->gopay());
    }

    #[Test]
    public function it_translates_shared_currencies_to_comgate_constants(): void
    {
        $this->assertSame(CurrencyCode::CZK, Currency::CZK->comgate());
        $this->assertSame(CurrencyCode::EUR, Currency::EUR->comgate());
        $this->assertSame(CurrencyCode::HRK, Currency::HRK->comgate());
        $this->assertSame(CurrencyCode::SEK, Currency::SEK->comgate());
    }

    #[Test]
    public function it_throws_when_currency_is_not_supported_by_gopay(): void
    {
        $this->expectException(UnsupportedFeatureException::class);
        Currency::HRK->gopay();
    }

    #[Test]
    public function it_throws_when_currency_is_not_supported_by_comgate(): void
    {
        $this->expectException(UnsupportedFeatureException::class);
        Currency::BGN->comgate();
    }
}
