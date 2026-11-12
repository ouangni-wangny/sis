<?php

namespace App\Domain\Shared\Enums;

enum StatutPlanningSemaine: string
{
    case Brouillon = 'brouillon';
    case Publiee = 'publiee';

    public function label(): string
    {
        return match ($this) {
            self::Brouillon => 'Brouillon',
            self::Publiee => 'Publiée',
        };
    }
}
