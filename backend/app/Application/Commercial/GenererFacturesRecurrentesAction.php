<?php

namespace App\Application\Commercial;

use App\Domain\Commercial\ConditionsCommerciales;
use App\Domain\Shared\Enums\PeriodiciteFacturation;
use App\Domain\Shared\Enums\StatutAbonnement;
use App\Domain\Shared\Enums\StatutFacture;
use App\Domain\Shared\Support\MontantEnLettres;
use App\Jobs\GenererFacturePdfJob;
use App\Models\Abonnement;
use App\Models\Facture;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Génère les factures d’abonnement à échéance (facturation d’avance).
 */
final class GenererFacturesRecurrentesAction
{
    /**
     * @return array{generated: int, skipped: int, errors: int}
     */
    public function execute(?Carbon $asOf = null): array
    {
        $today = ($asOf ?? now())->timezone('Africa/Abidjan')->startOfDay();
        $stats = ['generated' => 0, 'skipped' => 0, 'errors' => 0];

        Abonnement::query()
            ->where('statut', StatutAbonnement::Actif)
            ->whereNotNull('prochaine_facture_le')
            ->whereDate('prochaine_facture_le', '<=', $today->toDateString())
            ->with(['client', 'lignes'])
            ->orderBy('prochaine_facture_le')
            ->chunkById(50, function ($abonnements) use ($today, &$stats) {
                foreach ($abonnements as $abonnement) {
                    try {
                        $created = $this->genererPourAbonnement($abonnement, $today);
                        $stats['generated'] += $created;
                        if ($created === 0) {
                            $stats['skipped']++;
                        }
                    } catch (\Throwable $e) {
                        $stats['errors']++;
                        Log::error('Génération facture récurrente échouée', [
                            'abonnement_id' => $abonnement->id,
                            'message' => $e->getMessage(),
                        ]);
                    }
                }
            });

        return $stats;
    }

    private function genererPourAbonnement(Abonnement $abonnement, Carbon $today): int
    {
        return (int) DB::transaction(function () use ($abonnement, $today) {
            /** @var Abonnement $locked */
            $locked = Abonnement::query()
                ->whereKey($abonnement->id)
                ->lockForUpdate()
                ->with(['client', 'lignes'])
                ->firstOrFail();

            if ($locked->statut !== StatutAbonnement::Actif) {
                return 0;
            }

            if (! $locked->prochaine_facture_le) {
                return 0;
            }

            $periodicite = $locked->periodicite instanceof PeriodiciteFacturation
                ? $locked->periodicite
                : PeriodiciteFacturation::Mensuel;

            if ($locked->lignes->isEmpty()) {
                Log::warning('Abonnement sans lignes — facture récurrente ignorée', [
                    'abonnement_id' => $locked->id,
                ]);

                return 0;
            }

            $created = 0;
            $maxCatchUp = 24;

            while (
                $created < $maxCatchUp
                && $locked->prochaine_facture_le
                && $locked->prochaine_facture_le->lte($today)
            ) {
                $debut = $locked->prochaine_facture_le->format('Y-m-d');

                if ($locked->date_fin && $locked->prochaine_facture_le->gt($locked->date_fin)) {
                    $locked->update(['prochaine_facture_le' => null]);
                    break;
                }

                [$periodeDebut, $periodeFin] = ConditionsCommerciales::periodeFacturee(
                    $debut,
                    $periodicite,
                );

                if ($locked->date_fin && Carbon::parse($periodeDebut)->gt($locked->date_fin)) {
                    $locked->update(['prochaine_facture_le' => null]);
                    break;
                }

                $exists = Facture::query()
                    ->where('abonnement_id', $locked->id)
                    ->whereDate('periode_debut', $periodeDebut)
                    ->where('statut', '!=', StatutFacture::Annule)
                    ->exists();

                if (! $exists) {
                    $this->creerFacture($locked, $periodicite, $periodeDebut, $periodeFin);
                    $created++;
                }

                $locked->update([
                    'prochaine_facture_le' => ConditionsCommerciales::debutPeriodeSuivante(
                        $periodeDebut,
                        $periodicite,
                    ),
                ]);
                $locked->refresh();
            }

            return $created;
        });
    }

    private function creerFacture(
        Abonnement $abonnement,
        PeriodiciteFacturation $periodicite,
        string $periodeDebut,
        string $periodeFin,
    ): Facture {
        $client = $abonnement->client;
        $tauxTva = 18.0;
        $montantHt = 0.0;
        $prepared = [];

        foreach ($abonnement->lignes->values() as $i => $ligne) {
            $qte = round((float) $ligne->quantite, 2);
            $pu = round((float) $ligne->prix_unitaire, 2);
            $montant = round((float) $ligne->montant, 2);
            if ($montant <= 0) {
                $montant = round($qte * $pu, 2);
            }
            $montantHt += $montant;
            $prepared[] = [
                'offre_id' => $ligne->offre_id,
                'code_article' => null,
                'description' => $ligne->description,
                'quantite' => $qte,
                'prix_unitaire' => $pu,
                'montant' => $montant,
                'ordre' => $ligne->ordre ?? ($i + 1),
            ];
        }

        $montantTva = round($montantHt * ($tauxTva / 100), 2);
        $montantTtc = round($montantHt + $montantTva, 2);

        $dateEmission = now()->timezone('Africa/Abidjan')->toDateString();
        $lastFacture = Facture::query()
            ->where('abonnement_id', $abonnement->id)
            ->orderByDesc('date_emission')
            ->first();
        $delaiPaiementJours = (int) ($lastFacture?->delai_paiement_jours ?? 30);
        if (! in_array($delaiPaiementJours, ConditionsCommerciales::delaisPaiementJours(), true)) {
            $delaiPaiementJours = 30;
        }

        $numero = \App\Support\FactureNumero::next(
            \Illuminate\Support\Carbon::parse($dateEmission)
        );

        $facture = Facture::query()->create([
            'client_id' => $client->id,
            'abonnement_id' => $abonnement->id,
            'site_id' => $abonnement->site_id,
            'numero' => $numero,
            'date_emission' => $dateEmission,
            'date_echeance' => ConditionsCommerciales::dateEcheance($dateEmission, $delaiPaiementJours),
            'periode_debut' => $periodeDebut,
            'periode_fin' => $periodeFin,
            'periodicite' => $periodicite->value,
            'date_debut_service' => $abonnement->date_debut?->format('Y-m-d'),
            'date_fin_service' => $abonnement->date_fin?->format('Y-m-d'),
            'montant_ht' => $montantHt,
            'montant_tva' => $montantTva,
            'montant_ttc' => $montantTtc,
            'devise' => 'XOF',
            'statut' => StatutFacture::Valide,
            'lieu_emission' => 'Abidjan',
            'affaire_suivie_par' => (string) config('sis.commercial.affaire_suivie_par', 'SERVICE COMMERCIAL'),
            'telephone_commercial' => config('sis.commercial.telephone') ?: null,
            'taux_tva' => $tauxTva,
            'client_nom' => $client->raison_sociale,
            'client_adresse' => $client->adresse,
            'client_telephone' => $client->telephone,
            'client_email' => $client->email,
            'notes' => sprintf(
                'Facture récurrente générée automatiquement — période du %s au %s. %s',
                Carbon::parse($periodeDebut)->format('d/m/Y'),
                Carbon::parse($periodeFin)->format('d/m/Y'),
                ConditionsCommerciales::noteAbonnement($periodicite, $montantHt),
            ),
            'conditions_paiement' => ConditionsCommerciales::libelleDelaiPaiement($delaiPaiementJours),
            'delai_paiement_jours' => $delaiPaiementJours,
            'delai_validite' => null,
            'duree_contrat_min' => $lastFacture?->duree_contrat_min
                ?? 'Tous nos contrats sont conclus pour une durée minimum d’un an.',
            'signataire_nom' => $lastFacture?->signataire_nom,
            'signataire_fonction' => $lastFacture?->signataire_fonction ?? 'La Direction Commerciale',
            'montant_ttc_lettres' => MontantEnLettres::execute($montantTtc),
        ]);

        foreach ($prepared as $ligne) {
            $facture->lignes()->create($ligne);
        }

        GenererFacturePdfJob::dispatchSync($facture->id);

        return $facture;
    }
}
