<?php

namespace App\Application\Commercial;

use App\Domain\Shared\Enums\StatutFacture;
use App\Domain\Shared\Exceptions\FactureGenerationException;
use App\Jobs\GenererFacturePdfJob;
use App\Models\Client;
use App\Models\Facture;
use App\Models\Vacation;
use Illuminate\Support\Facades\DB;

final class GenererFactureDepuisVacationsAction
{
    public function execute(string $clientId, string $dateDebut, string $dateFin): Facture
    {
        return DB::transaction(function () use ($clientId, $dateDebut, $dateFin) {
            $client = Client::query()->findOrFail($clientId);

            $dejaFacture = Facture::query()
                ->where('client_id', $clientId)
                ->whereNotIn('statut', [StatutFacture::Annule])
                ->whereNotNull('periode_debut')
                ->whereNotNull('periode_fin')
                ->whereDate('periode_debut', '<=', $dateFin)
                ->whereDate('periode_fin', '>=', $dateDebut)
                ->exists();

            if ($dejaFacture) {
                throw new FactureGenerationException(
                    'Une facture non annulée existe déjà pour ce client sur une période qui chevauche.'
                );
            }

            $vacations = Vacation::query()
                ->whereHas('site', fn ($q) => $q->where('client_id', $clientId))
                ->whereBetween('date_debut', [$dateDebut, $dateFin])
                ->whereNotIn('statut', ['annulee'])
                ->with('site')
                ->get();

            if ($vacations->isEmpty()) {
                throw new FactureGenerationException(
                    'Aucune vacation facturable sur cette période pour ce client.'
                );
            }

            $numero = 'FAC-'.now()->format('Ymd').'-'.strtoupper(substr(uniqid(), -5));

            $lignes = [];
            $montantHt = 0;

            foreach ($vacations->groupBy('site_id') as $siteVacations) {
                $site = $siteVacations->first()->site;
                $tarif = (float) ($site->tarif_mensuel ?? 0);
                $count = $siteVacations->count();
                $montant = $tarif > 0 ? $tarif : ($count * 5000);
                $lignes[] = [
                    'description' => "Prestations site {$site->nom} ({$count} vacation(s))",
                    'quantite' => $count,
                    'prix_unitaire' => $count > 0 ? round($montant / $count, 2) : 0,
                    'montant' => $montant,
                ];
                $montantHt += $montant;
            }

            $tva = 0;
            $ttc = $montantHt + $tva;

            $facture = Facture::query()->create([
                'client_id' => $client->id,
                'numero' => $numero,
                'date_emission' => now()->toDateString(),
                'date_echeance' => now()->addDays(30)->toDateString(),
                'periode_debut' => $dateDebut,
                'periode_fin' => $dateFin,
                'montant_ht' => $montantHt,
                'montant_tva' => $tva,
                'montant_ttc' => $ttc,
                'devise' => 'XOF',
                'statut' => StatutFacture::EnAttente,
            ]);

            foreach ($lignes as $ligne) {
                $facture->lignes()->create($ligne);
            }

            GenererFacturePdfJob::dispatchSync($facture->id);

            return $facture->fresh()->load(['lignes', 'client', 'media']);
        });
    }
}
