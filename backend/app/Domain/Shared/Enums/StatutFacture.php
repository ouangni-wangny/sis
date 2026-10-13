<?php

namespace App\Domain\Shared\Enums;

enum StatutFacture: string
{
    case EnAttente = 'en_attente';
    case Valide = 'valide';
    /** Proforma refusée (jamais émise comme facture définitive). */
    case Rejetee = 'rejetee';
    /** Facture validée annulée (éventuellement après encaissement). */
    case Annulee = 'annulee';

    public function label(): string
    {
        return match ($this) {
            self::EnAttente => 'En attente',
            self::Valide => 'Validé',
            self::Rejetee => 'Rejetée',
            self::Annulee => 'Annulée',
        };
    }

    /** Document « mort » : plus modifiable / plus payable. */
    public function estCloturee(): bool
    {
        return $this === self::Rejetee || $this === self::Annulee;
    }
}
