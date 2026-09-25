<?php

namespace App\Domain\Shared\Enums;

enum ModePaiement: string
{
    case Especes = 'especes';
    case Virement = 'virement';
    case Cheque = 'cheque';
    case Wave = 'wave';
    case Mtn = 'mtn';
    case Moov = 'moov';
    case Orange = 'orange';
    /** @deprecated Prefer Wave / MTN / Moov / Orange */
    case MobileMoney = 'mobile_money';
    case Autre = 'autre';

    public function label(): string
    {
        return match ($this) {
            self::Especes => 'Espèces',
            self::Virement => 'Virement',
            self::Cheque => 'Chèque',
            self::Wave => 'Wave',
            self::Mtn => 'MTN Money',
            self::Moov => 'Moov Money',
            self::Orange => 'Orange Money',
            self::MobileMoney => 'Mobile Money',
            self::Autre => 'Autre',
        };
    }
}
