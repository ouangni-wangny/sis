<?php

namespace App\Application\Commercial;

use App\Domain\Shared\Enums\StatutAbonnement;
use App\Models\Abonnement;
use App\Models\Offre;
use App\Models\Site;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpsertAbonnementAction
{
    public function create(array $data): Abonnement
    {
        return DB::transaction(function () use ($data) {
            if (empty($data['periodicite'])) {
                $data['periodicite'] = \App\Domain\Shared\Enums\PeriodiciteFacturation::Mensuel->value;
            }
            $this->assertRules($data);

            if (empty($data['prochaine_facture_le']) && ! empty($data['date_debut'])) {
                // Sans facture initiale : première facture due au début du contrat.
                $data['prochaine_facture_le'] = $data['date_debut'];
            }

            return Abonnement::query()->create($data)->load(['client', 'offre', 'site']);
        });
    }

    public function update(Abonnement $abonnement, array $data): Abonnement
    {
        return DB::transaction(function () use ($abonnement, $data) {
            $merged = [
                'client_id' => $data['client_id'] ?? $abonnement->client_id,
                'offre_id' => array_key_exists('offre_id', $data)
                    ? $data['offre_id']
                    : $abonnement->offre_id,
                'designation' => array_key_exists('designation', $data)
                    ? $data['designation']
                    : $abonnement->designation,
                'site_id' => array_key_exists('site_id', $data) ? $data['site_id'] : $abonnement->site_id,
                'periodicite' => $data['periodicite'] ?? $abonnement->periodicite->value,
                'date_debut' => $data['date_debut'] ?? $abonnement->date_debut->format('Y-m-d'),
                'date_fin' => array_key_exists('date_fin', $data)
                    ? $data['date_fin']
                    : $abonnement->date_fin?->format('Y-m-d'),
                'statut' => $data['statut'] ?? $abonnement->statut->value,
            ];

            $this->assertRules($merged, $abonnement->id);
            $abonnement->update($data);

            return $abonnement->fresh(['client', 'offre', 'site']);
        });
    }

    private function assertRules(array $data, ?string $exceptId = null): void
    {
        if (! empty($data['site_id'])) {
            $ok = Site::query()
                ->where('id', $data['site_id'])
                ->where('client_id', $data['client_id'])
                ->exists();

            if (! $ok) {
                throw ValidationException::withMessages([
                    'site_id' => 'Ce site n’appartient pas au client sélectionné.',
                ]);
            }
        }

        $offreId = $data['offre_id'] ?? null;
        $designation = trim((string) ($data['designation'] ?? ''));

        if (blank($offreId) && $designation === '') {
            throw ValidationException::withMessages([
                'offre_id' => 'Choisissez une offre, ou indiquez une désignation pour un abonnement combiné.',
            ]);
        }

        $statut = (string) ($data['statut'] ?? StatutAbonnement::Actif->value);

        if (filled($offreId)) {
            $offre = Offre::query()->findOrFail($offreId);

            if (! $offre->actif && $statut === StatutAbonnement::Actif->value) {
                throw ValidationException::withMessages([
                    'offre_id' => 'Impossible d’utiliser une offre inactive pour un abonnement actif.',
                ]);
            }
        }

        if ($statut !== StatutAbonnement::Actif->value) {
            return;
        }

        $debut = (string) $data['date_debut'];
        $fin = blank($data['date_fin'] ?? null) ? null : (string) $data['date_fin'];

        $overlap = Abonnement::query()
            ->where('client_id', $data['client_id'])
            ->where('statut', StatutAbonnement::Actif)
            ->when(
                filled($offreId),
                fn ($q) => $q->where('offre_id', $offreId),
                fn ($q) => $q->whereNull('offre_id')->where('designation', $designation),
            )
            ->when(
                ! empty($data['site_id']),
                fn ($q) => $q->where('site_id', $data['site_id']),
                fn ($q) => $q->whereNull('site_id'),
            )
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->whereDate('date_debut', '<=', $fin ?? '9999-12-31')
            ->where(function ($q) use ($debut) {
                $q->whereNull('date_fin')->orWhereDate('date_fin', '>=', $debut);
            })
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages([
                'offre_id' => 'Un abonnement actif chevauche déjà cette période pour ce client'
                    .(filled($offreId) ? ' / offre' : ' / combiné')
                    .(! empty($data['site_id']) ? ' / site' : '').'.',
            ]);
        }
    }
}
