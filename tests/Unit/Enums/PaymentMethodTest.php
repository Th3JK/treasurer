<?php

namespace Th3JK\Treasurer\Tests\Unit\Enums;

use Comgate\SDK\Entity\Codes\PaymentMethodCode;
use GoPay\Definition\Payment\PaymentInstrument;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Th3JK\Treasurer\Enums\PaymentMethod;
use Th3JK\Treasurer\Exceptions\UnsupportedFeatureException;

class PaymentMethodTest extends TestCase
{
    #[Test]
    public function it_translates_methods_to_gopay_instruments(): void
    {
        $this->assertSame(PaymentInstrument::PAYMENT_CARD, PaymentMethod::CARD->gopay());
        $this->assertSame(PaymentInstrument::BANK_ACCOUNT, PaymentMethod::BANK->gopay());
        $this->assertSame(PaymentInstrument::APPLE_PAY, PaymentMethod::APPLE_PAY->gopay());
        $this->assertSame(PaymentInstrument::GPAY, PaymentMethod::GOOGLE_PAY->gopay());
        $this->assertSame(PaymentInstrument::BITCOIN, PaymentMethod::BITCOIN->gopay());
    }

    #[Test]
    public function it_translates_methods_to_comgate_codes(): void
    {
        $this->assertSame(PaymentMethodCode::ALL_CARDS, PaymentMethod::CARD->comgate());
        $this->assertSame(PaymentMethodCode::ALL_BANKS, PaymentMethod::BANK->comgate());
        $this->assertSame(PaymentMethodCode::APPLEPAY_REDIRECT, PaymentMethod::APPLE_PAY->comgate());
        $this->assertSame(PaymentMethodCode::GOOGLEPAY_REDIRECT, PaymentMethod::GOOGLE_PAY->comgate());
        $this->assertSame(PaymentMethodCode::LATER_ALL, PaymentMethod::LATER->comgate());
    }

    #[Test]
    public function it_throws_when_method_is_not_supported_by_gopay(): void
    {
        $this->expectException(UnsupportedFeatureException::class);
        PaymentMethod::LATER->gopay();
    }

    #[Test]
    public function it_throws_when_method_is_not_supported_by_comgate(): void
    {
        $this->expectException(UnsupportedFeatureException::class);
        PaymentMethod::BITCOIN->comgate();
    }
}
