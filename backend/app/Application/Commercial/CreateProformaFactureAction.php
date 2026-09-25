<?php

namespace App\Application\Commercial;

use App\Domain\Commercial\ConditionsCommerciales;
use App\Domain\Shared\Enums\PeriodiciteFacturation;
use App\Domain\Shared\Enums\StatutAbonnement;
use App\Domain\Shared\Enums\StatutFacture;
use App\Domain\Shared\Support\MontantEnLettres;
use App\Jobs\GenererFacturePdfJob;
use App\Models\Abonnement;
use App\Models\Client;
use App\Models\Facture;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateProformaFactureAction
{
    public function __construct(
        private readonly UpsertAbonnementAction $upsertAbonnement,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): Facture
    {
        return DB::transaction(function () use ($data) {
            $clientId = filled($data['client_id'] ?? null) ? (string) $data['client_id'] : null;
            $client = $clientId ? Client::query()->findOrFail($clientId) : null;
            $clientNom = trim((string) ($data['client_nom'] ?? ''));

            if (! $client && $clientNom === '') {
                throw ValidationException::withMessages([
                    'client_nom' => 'Indiquez un client du système ou le nom du destinataire.',
                ]);
            }

            $lignes = $data['lignes'] ?? [];

            if ($lignes === []) {
                throw ValidationException::withMessages([
                    'lignes' => 'Ajoutez au moins une ligne de prestation.',
                ]);
            }

            $tauxTva = 18.0;
            $montantHt = 0.0;
            $prepared = [];

            foreach (array_values($lignes) as $i => $ligne) {
                $qte = round((float) $ligne['quantite'], 2);
                $pu = round((float) $ligne['prix_unitaire'], 2);
                if ($qte <= 0) {
                    throw ValidationException::withMessages([
                        "lignes.$i.quantite" => 'La quantité doit être > 0.',
                    ]);
                }
                $montant = round($qte * $pu, 2);
                $montantHt += $montant;
                $prepared[] = [
                    'offre_id' => filled($ligne['offre_id'] ?? null) ? (string) $ligne['offre_id'] : null,
                    'code_article' => $ligne['code_article'] ?? null,
                    'description' => $ligne['description'],
                    'quantite' => $qte,
                    'prix_unitaire' => $pu,
                    'montant' => $montant,
                    'ordre' => $i + 1,
                ];
            }

            $montantTva = round($montantHt * ($tauxTva / 100), 2);
            $montantTtc = round($montantHt + $montantTva, 2);

            $dateEmission = now()->timezone('Africa/Abidjan')->toDateString();
            $delaiValidite = $data['delai_validite'] ?? '1 mois';
            $delaiPaiementJours = (int) ($data['delai_paiement_jours'] ?? 0);
            if (! in_array($delaiPaiementJours, ConditionsCommerciales::delaisPaiementJours(), true)) {
                throw ValidationException::withMessages([
                    'delai_paiement_jours' => 'Délai de paiement invalide.',
                ]);
            }

            $periodicite = isset($data['periodicite']) && $data['periodicite'] !== '' && $data['periodicite'] !== null
                ? PeriodiciteFacturation::from((string) $data['periodicite'])
                : null;

            $dateDebutService = filled($data['date_debut_service'] ?? null)
                ? (string) $data['date_debut_service']
                : $dateEmission;
            $dateFinService = filled($data['date_fin_service'] ?? null)
                ? (string) $data['date_fin_service']
                : null;

            $periodeDebut = null;
            $periodeFin = null;
            if ($periodicite) {
                [$periodeDebut, $periodeFin] = ConditionsCommerciales::periodeFacturee(
                    $dateDebutService,
                    $periodicite,
                );
            }

            $conditionsPaiement = filled($data['conditions_paiement'] ?? null)
                ? (string) $data['conditions_paiement']
                : ConditionsCommerciales::libelleDelaiPaiement($delaiPaiementJours);

            $notes = filled($data['notes'] ?? null)
                ? (string) $data['notes']
                : ($periodicite
                    ? ConditionsCommerciales::noteAbonnement($periodicite, $montantHt)
                    : null);

            $abonnementId = filled($data['abonnement_id'] ?? null)
                ? (string) $data['abonnement_id']
                : null;

            if ($abonnementId) {
                if (! $client) {
                    throw ValidationException::withMessages([
                        'abonnement_id' => 'Un abonnement ne peut être lié qu’à un client du système.',
                    ]);
                }
                $abonnement = Abonnement::query()->findOrFail($abonnementId);
                if ($abonnement->client_id !== $client->id) {
                    throw ValidationException::withMessages([
                        'abonnement_id' => 'Cet abonnement n’appartient pas au client sélectionné.',
                    ]);
                }
                $periodicite ??= $abonnement->periodicite;
                if ($periodicite && ! $periodeDebut) {
                    [$periodeDebut, $periodeFin] = ConditionsCommerciales::periodeFacturee(
                        $abonnement->date_debut->format('Y-m-d'),
                        $periodicite,
                    );
                }
            } elseif (! empty($data['creer_abonnement'])) {
                if (! $client) {
                    throw ValidationException::withMessages([
                        'creer_abonnement' => 'Impossible de créer un abonnement sans client du système.',
                    ]);
                }
                $offreIds = array_values(array_filter(array_map(
                    'strval',
                    $data['offre_ids'] ?? (filled($data['offre_id'] ?? null) ? [$data['offre_id']] : [])
                )));

                if ($offreIds === [] && $prepared === []) {
                    throw ValidationException::withMessages([
                        'offre_ids' => 'Ajoutez des lignes pour créer l’abonnement.',
                    ]);
                }
                if (! $periodicite) {
                    throw ValidationException::withMessages([
                        'periodicite' => 'La périodicité est requise pour créer l’abonnement.',
                    ]);
                }

                $libelles = $offreIds === []
                    ? []
                    : \App\Models\Offre::query()
                        ->whereIn('id', $offreIds)
                        ->orderBy('libelle')
                        ->pluck('libelle')
                        ->all();

                $designation = $libelles !== []
                    ? implode(' + ', $libelles)
                    : implode(' + ', array_unique(array_column($prepared, 'description')));

                $abonnement = $this->upsertAbonnement->create([
                    'client_id' => $client->id,
                    'offre_id' => count($offreIds) === 1 ? $offreIds[0] : null,
                    'designation' => $designation !== '' ? $designation : 'Abonnement',
                    'site_id' => filled($data['site_id'] ?? null) ? $data['site_id'] : null,
                    'periodicite' => $periodicite->value,
                    'date_debut' => $dateDebutService,
                    'date_fin' => $dateFinService,
                    // Première période couverte par cette proforma.
                    'prochaine_facture_le' => ConditionsCommerciales::debutPeriodeSuivante(
                        $dateDebutService,
                        $periodicite,
                    ),
                    'statut' => StatutAbonnement::Actif->value,
                ]);
                $abonnementId = $abonnement->id;

                foreach ($prepared as $index => $ligne) {
                    $abonnement->lignes()->create([
                        'offre_id' => $ligne['offre_id'] ?? null,
                        'description' => $ligne['description'],
                        'quantite' => $ligne['quantite'],
                        'prix_unitaire' => $ligne['prix_unitaire'],
                        'montant' => $ligne['montant'],
                        'ordre' => $ligne['ordre'] ?? ($index + 1),
                    ]);
                }
            }

            $numero = \App\Support\FactureNumero::next(
                \Illuminate\Support\Carbon::parse($dateEmission)
            );

            $facture = Facture::query()->create([
                'client_id' => $client?->id,
                'abonnement_id' => $abonnementId,
                'site_id' => $client && filled($data['site_id'] ?? null) ? $data['site_id'] : null,
                'numero' => $numero,
                'date_emission' => $dateEmission,
                'date_echeance' => ConditionsCommerciales::dateEcheance($dateEmission, $delaiPaiementJours),
                'periode_debut' => $periodeDebut,
                'periode_fin' => $periodeFin,
                'periodicite' => $periodicite?->value,
                'date_debut_service' => $dateDebutService,
                'date_fin_service' => $dateFinService,
                'montant_ht' => $montantHt,
                'montant_tva' => $montantTva,
                'montant_ttc' => $montantTtc,
                'devise' => 'XOF',
                'statut' => StatutFacture::EnAttente,
                'lieu_emission' => 'Abidjan',
                'affaire_suivie_par' => (string) config('sis.commercial.affaire_suivie_par', 'SERVICE COMMERCIAL'),
                'telephone_commercial' => config('sis.commercial.telephone') ?: null,
                'taux_tva' => $tauxTva,
                'client_nom' => $client?->raison_sociale ?? $clientNom,
                'client_adresse' => $client?->adresse
                    ?? (filled($data['client_adresse'] ?? null) ? (string) $data['client_adresse'] : null),
                'client_telephone' => $client?->telephone
                    ?? (filled($data['client_telephone'] ?? null) ? (string) $data['client_telephone'] : null),
                'client_email' => $client?->email
                    ?? (filled($data['client_email'] ?? null) ? (string) $data['client_email'] : null),
                'notes' => $notes,
                'conditions_paiement' => $conditionsPaiement,
                'delai_paiement_jours' => $delaiPaiementJours,
                'delai_validite' => $delaiValidite,
                'duree_contrat_min' => $data['duree_contrat_min']
                    ?? 'Tous nos contrats sont conclus pour une durée minimum d’un an.',
                'signataire_nom' => $data['signataire_nom'] ?? null,
                'signataire_fonction' => $data['signataire_fonction'] ?? 'La Direction Commerciale',
                'montant_ttc_lettres' => MontantEnLettres::execute($montantTtc),
            ]);

            foreach ($prepared as $ligne) {
                $facture->lignes()->create($ligne);
            }

            GenererFacturePdfJob::dispatchSync($facture->id);

            return $facture->fresh()->load(['lignes', 'client', 'abonnement', 'media']);
        });
    }
}
