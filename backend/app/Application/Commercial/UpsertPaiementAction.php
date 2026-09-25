<?php

namespace App\Application\Commercial;

use App\Application\Tresorerie\PosterMouvementTresorerieAction;
use App\Domain\Shared\Enums\DirectionMouvementTresorerie;
use App\Domain\Shared\Enums\SourceMouvementTresorerie;
use App\Domain\Shared\Enums\StatutFacture;
use App\Models\Facture;
use App\Models\Paiement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class UpsertPaiementAction
{
    private const TOLERANCE = 1.0;

    public function __construct(
        private readonly PosterMouvementTresorerieAction $poster,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Paiement
    {
        return DB::transaction(function () use ($data) {
            $facture = Facture::query()->findOrFail($data['facture_id']);
            $this->assertFacturePayable($facture);
            $this->assertMontant($facture, (float) $data['montant']);
            $this->assertCompteSiTresorerie($data);

            $data['reference'] = $this->resolveReference($data['reference'] ?? null);

            $paiement = Paiement::query()->create($data)->load(['facture.client', 'compteTresorerie']);
            $this->postEncaissement($paiement);

            return $paiement;
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

            if (array_key_exists('reference', $data)) {
                $data['reference'] = $this->resolveReference(
                    $data['reference'],
                    $paiement->reference,
                );
            }

            $merged = array_merge($paiement->only([
                'facture_id', 'montant', 'date_paiement', 'mode', 'compte_tresorerie_id', 'reference', 'notes',
            ]), $data);
            $this->assertCompteSiTresorerie($merged);

            if (PosterMouvementTresorerieAction::isModuleEnabled()) {
                // Correction : on retire l’ancien mouvement puis on re-poste (pas d’inverse,
                // sinon le solde serait faussé avec le soft-delete nécessaire au re-post).
                $this->poster->retractForSource(
                    SourceMouvementTresorerie::FacturePaiement,
                    $paiement->id,
                );
            }

            $paiement->update($data);
            $paiement = $paiement->fresh(['facture.client', 'compteTresorerie']);
            $this->postEncaissement($paiement);

            return $paiement;
        });
    }

    public function delete(Paiement $paiement): void
    {
        DB::transaction(function () use ($paiement) {
            if (PosterMouvementTresorerieAction::isModuleEnabled()) {
                $this->poster->reverseForSource(
                    SourceMouvementTresorerie::FacturePaiement,
                    $paiement->id,
                    'Suppression encaissement facture',
                );
            }
            $paiement->delete();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertCompteSiTresorerie(array $data): void
    {
        if (! PosterMouvementTresorerieAction::isModuleEnabled()) {
            return;
        }

        if (empty($data['compte_tresorerie_id'])) {
            throw ValidationException::withMessages([
                'compte_tresorerie_id' => 'Le compte de trésorerie est requis.',
            ]);
        }
    }

    private function postEncaissement(Paiement $paiement): void
    {
        if (! PosterMouvementTresorerieAction::isModuleEnabled()) {
            return;
        }

        if (! $paiement->compte_tresorerie_id) {
            return;
        }

        $this->poster->execute([
            'compte_tresorerie_id' => $paiement->compte_tresorerie_id,
            'direction' => DirectionMouvementTresorerie::Entree,
            'montant' => $paiement->montant,
            'date_mouvement' => $paiement->date_paiement->format('Y-m-d'),
            'mode' => $paiement->mode,
            'source_type' => SourceMouvementTresorerie::FacturePaiement,
            'source_id' => $paiement->id,
            'reference' => $paiement->reference,
            'notes' => 'Encaissement facture',
        ]);
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

    private function resolveReference(mixed $reference, ?string $fallback = null): string
    {
        $value = trim((string) ($reference ?? ''));
        if ($value !== '') {
            return $value;
        }

        $keep = trim((string) ($fallback ?? ''));
        if ($keep !== '') {
            return $keep;
        }

        return 'ENC-'.now()->format('Ymd').'-'.Str::upper(Str::random(5));
    }
}
