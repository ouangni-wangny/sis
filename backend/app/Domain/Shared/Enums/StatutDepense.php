<?php

namespace App\Domain\Shared\Enums;

enum StatutDepense: string
{
    case Validee = 'validee';
    case Annulee = 'annulee';

    public function label(): string
    {
        return match ($this) {
            self::Validee => 'Validée',
            self::Annulee => 'Annulée',
        };
    }
}
