<?php

namespace Th3JK\Treasurer\Tests\Unit\Enums;

use Comgate\SDK\Entity\Codes\LangCode;
use GoPay\Definition\Language as GoPayLanguage;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Th3JK\Treasurer\Enums\Language;

class LanguageTest extends TestCase
{
    #[Test]
    public function it_translates_to_gopay_uppercase_codes(): void
    {
        $this->assertSame(GoPayLanguage::CZECH, Language::CS->gopay());
        $this->assertSame(GoPayLanguage::ENGLISH, Language::EN->gopay());
        $this->assertSame(GoPayLanguage::CROATIAN, Language::HR->gopay());
    }

    #[Test]
    public function it_translates_to_comgate_lowercase_codes(): void
    {
        $this->assertSame(LangCode::CS, Language::CS->comgate());
        $this->assertSame(LangCode::EN, Language::EN->comgate());
        $this->assertSame(LangCode::HR, Language::HR->comgate());
    }
}
