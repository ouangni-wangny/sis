<?php

namespace App\Domain\Shared\Enums;

enum SourceMouvementTresorerie: string
{
    case FacturePaiement = 'facture_paiement';
    case BulletinPaie = 'bulletin_paie';
    case AvanceSalaire = 'avance_salaire';
    case Depense = 'depense';
    case Ajustement = 'ajustement';
    case Transfert = 'transfert';

    public function label(): string
    {
        return match ($this) {
            self::FacturePaiement => 'Encaissement facture',
            self::BulletinPaie => 'Règlement paie',
            self::AvanceSalaire => 'Avance sur salaire',
            self::Depense => 'Dépense',
            self::Ajustement => 'Ajustement',
            self::Transfert => 'Transfert interne',
        };
    }
}
