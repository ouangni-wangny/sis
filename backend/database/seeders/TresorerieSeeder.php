<?php

namespace Database\Seeders;

use App\Domain\Shared\Enums\TypeCompteTresorerie;
use App\Models\CategorieDepense;
use App\Models\CompteTresorerie;
use Illuminate\Database\Seeder;

class TresorerieSeeder extends Seeder
{
    public function run(): void
    {
        $comptes = [
            ['libelle' => 'Banque principale', 'type' => TypeCompteTresorerie::Banque],
            ['libelle' => 'Caisse siège', 'type' => TypeCompteTresorerie::Caisse],
            ['libelle' => 'Mobile Money', 'type' => TypeCompteTresorerie::MobileMoney],
        ];

        foreach ($comptes as $row) {
            CompteTresorerie::query()->firstOrCreate(
                ['libelle' => $row['libelle']],
                [
                    'type' => $row['type'],
                    'solde_ouverture' => 0,
                    'actif' => true,
                ],
            );
        }

        $categories = [
            'Loyer',
            'Carburant',
            'Fournitures',
            'Maintenance',
            'Télécom',
            'Assurances',
            'Divers',
        ];

        foreach ($categories as $libelle) {
            CategorieDepense::query()->firstOrCreate(
                ['libelle' => $libelle],
                ['actif' => true],
            );
        }
    }
}
