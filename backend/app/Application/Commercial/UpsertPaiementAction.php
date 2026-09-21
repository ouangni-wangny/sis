<?php

namespace App\Application\Commercial;

use App\Domain\Shared\Enums\StatutFacture;
use App\Models\Facture;
use App\Models\Paiement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpsertPaiementAction
{
    private const TOLERANCE = 1.0;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Paiement
    {
        return DB::transaction(function () use ($data) {
            $facture = Facture::query()->findOrFail($data['facture_id']);
            $this->assertFacturePayable($facture);
            $this->assertMontant($facture, (float) $data['montant']);

            return Paiement::query()->create($data)->load(['facture.client']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Paiement $paiement, array $data): Paiement
    {
        return DB::transaction(function () use ($paiement, $data) {
            $factureId = $data['facture_id'] ?? $paiement->facture_id;
            $facture = Facture::query()->findOrFail($factureId);
            $this->assertFacturePayable($facture);

            $montant = (float) ($data['montant'] ?? $paiement->montant);
            $this->assertMontant($facture, $montant, $paiement->id);

            $paiement->update($data);

            return $paiement->fresh(['facture.client']);
        });
    }

    private function assertFacturePayable(Facture $facture): void
    {
        if ($facture->statut === StatutFacture::Annule) {
            throw ValidationException::withMessages([
                'facture_id' => 'Impossible d’enregistrer un paiement sur une facture annulée.',
            ]);
        }
    }

    private function assertMontant(Facture $facture, float $montant, ?string $exceptId = null): void
    {
        if ($montant <= 0) {
            throw ValidationException::withMessages([
                'montant' => 'Le montant doit être supérieur à 0.',
            ]);
        }

        $dejaPaye = (float) $facture->paiements()
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->sum('montant');

        $ttc = (float) $facture->montant_ttc;
        if ($dejaPaye + $montant > $ttc + self::TOLERANCE) {
            $solde = max(0, round($ttc - $dejaPaye, 2));
            throw ValidationException::withMessages([
                'montant' => "Le montant dépasse le solde restant ({$solde} FCFA).",
            ]);
        }
    }
}
