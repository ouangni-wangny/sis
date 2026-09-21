<?php

namespace App\Domain\Shared\Enums;

enum StatutFacture: string
{
    case EnAttente = 'en_attente';
    case Valide = 'valide';
    case Annule = 'annule';

    public function label(): string
    {
        return match ($this) {
            self::EnAttente => 'En attente',
            self::Valide => 'Validé',
            self::Annule => 'Annulé',
        };
    }
}
