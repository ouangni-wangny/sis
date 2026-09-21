<?php

use App\Models\Abonnement;
use App\Models\Facture;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Abonnement::query()
            ->whereDoesntHave('lignes')
            ->with(['factures' => fn ($q) => $q->with('lignes')->oldest()])
            ->each(function (Abonnement $abonnement) {
                $facture = $abonnement->factures->first();
                if (! $facture) {
                    $facture = Facture::query()
                        ->where('abonnement_id', $abonnement->id)
                        ->with('lignes')
                        ->oldest()
                        ->first();
                }
                if (! $facture || $facture->lignes->isEmpty()) {
                    return;
                }

                foreach ($facture->lignes as $index => $ligne) {
                    $abonnement->lignes()->create([
                        'offre_id' => $ligne->offre_id,
                        'description' => $ligne->description,
                        'quantite' => $ligne->quantite,
                        'prix_unitaire' => $ligne->prix_unitaire,
                        'montant' => $ligne->montant,
                        'ordre' => $ligne->ordre ?? ($index + 1),
                    ]);
                }
            });
    }

    public function down(): void
    {
        //
    }
};
