<?php

namespace App\Domain\Shared\Enums;

enum SourceMouvementTresorerie: string
{
    case FacturePaiement = 'facture_paiement';
    case BulletinPaie = 'bulletin_paie';
    case Depense = 'depense';
    case Ajustement = 'ajustement';

    public function label(): string
    {
        return match ($this) {
            self::FacturePaiement => 'Encaissement facture',
            self::BulletinPaie => 'Règlement paie',
            self::Depense => 'Dépense',
            self::Ajustement => 'Ajustement',
        };
    }
}
