<?php

namespace Th3JK\Treasurer\Enums;

use Comgate\SDK\Entity\Codes\LangCode;
use GoPay\Definition\Language as GoPayLanguage;

enum Language: string
{
    case CS = 'CS';
    case SK = 'SK';
    case EN = 'EN';
    case DE = 'DE';
    case PL = 'PL';
    case HU = 'HU';
    case RO = 'RO';
    case FR = 'FR';
    case HR = 'HR';

    public function gopay(): string
    {
        return match ($this) {
            self::CS => GoPayLanguage::CZECH,
            self::SK => GoPayLanguage::SLOVAK,
            self::EN => GoPayLanguage::ENGLISH,
            self::DE => GoPayLanguage::GERMAN,
            self::PL => GoPayLanguage::POLISH,
            self::HU => GoPayLanguage::HUNGARIAN,
            self::RO => GoPayLanguage::ROMANIAN,
            self::FR => GoPayLanguage::FRENCH,
            self::HR => GoPayLanguage::CROATIAN,
        };
    }

    public function comgate(): string
    {
        return match ($this) {
            self::CS => LangCode::CS,
            self::SK => LangCode::SK,
            self::EN => LangCode::EN,
            self::DE => LangCode::DE,
            self::PL => LangCode::PL,
            self::HU => LangCode::HU,
            self::RO => LangCode::RO,
            self::FR => LangCode::FR,
            self::HR => LangCode::HR,
        };
    }
}
