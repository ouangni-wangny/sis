<?php

namespace App\Domain\Shared\Enums;

enum DirectionMouvementTresorerie: string
{
    /** Encaissement client (paiement de facture uniquement). */
    case Entree = 'entree';
    /** Sortie de cash (dépense, paie, transfert sortant, ajustement négatif…). */
    case Sortie = 'sortie';
    /** Remise / crédit hors encaissement client (ajustement positif). */
    case Retour = 'retour';
    /** Crédit issu d’un transfert interne compte → compte. */
    case Approvisionnement = 'approvisionnement';

    public function label(): string
    {
        return match ($this) {
            self::Entree => 'Entrée',
            self::Sortie => 'Sortie',
            self::Retour => 'Retour',
            self::Approvisionnement => 'Approvisionnement',
        };
    }

    /** Sens qui augmente le solde du compte. */
    public function crediteLeCompte(): bool
    {
        return match ($this) {
            self::Entree, self::Retour, self::Approvisionnement => true,
            self::Sortie => false,
        };
    }

    public function inverse(): self
    {
        return match ($this) {
            self::Entree => self::Sortie,
            self::Sortie => self::Retour,
            self::Retour, self::Approvisionnement => self::Sortie,
        };
    }
}
