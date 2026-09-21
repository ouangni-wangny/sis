<?php

namespace App\Domain\Shared\Enums;

enum StatutPaiementFacture: string
{
    case NonPayee = 'non_payee';
    case Partiel = 'partiel';
    case Soldee = 'soldee';

    public function label(): string
    {
        return match ($this) {
            self::NonPayee => 'Non payée',
            self::Partiel => 'Partiel',
            self::Soldee => 'Soldée',
        };
    }
}
