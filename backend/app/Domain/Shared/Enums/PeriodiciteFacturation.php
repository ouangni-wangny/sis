<?php

namespace App\Domain\Shared\Enums;

enum PeriodiciteFacturation: string
{
    case Mensuel = 'mensuel';
    case Trimestriel = 'trimestriel';
    case Annuel = 'annuel';

    public function mois(): int
    {
        return match ($this) {
            self::Mensuel => 1,
            self::Trimestriel => 3,
            self::Annuel => 12,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Mensuel => 'mensuel',
            self::Trimestriel => 'trimestriel',
            self::Annuel => 'annuel',
        };
    }

    public function labelFr(): string
    {
        return match ($this) {
            self::Mensuel => 'Mensuel',
            self::Trimestriel => 'Trimestriel',
            self::Annuel => 'Annuel',
        };
    }
}
