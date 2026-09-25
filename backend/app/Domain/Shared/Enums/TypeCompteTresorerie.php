<?php

namespace App\Domain\Shared\Enums;

enum TypeCompteTresorerie: string
{
    case Banque = 'banque';
    case Caisse = 'caisse';
    case MobileMoney = 'mobile_money';

    public function label(): string
    {
        return match ($this) {
            self::Banque => 'Banque',
            self::Caisse => 'Caisse',
            self::MobileMoney => 'Mobile Money',
        };
    }
}
