<?php

namespace App\Application\Commercial;

use App\Domain\Commercial\ConditionsCommerciales;
use App\Domain\Shared\Enums\PeriodiciteFacturation;
use App\Domain\Shared\Enums\StatutFacture;
use App\Domain\Shared\Support\MontantEnLettres;
use App\Jobs\GenererFacturePdfJob;
use App\Models\Abonnement;
use App\Models\Client;
use App\Models\Facture;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateProformaFactureAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Facture $facture, array $data): Facture
    {
        return DB::transaction(function () use ($facture, $data) {
            if ($facture->statut === StatutFacture::Annule) {
                throw ValidationException::withMessages([
                    'facture' => 'Impossible de modifier une facture annulée.',
                ]);
            }

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
            $delaiValidite = $data['delai_validite'] ?? $facture->delai_validite ?? '1 mois';
            $delaiPaiementJours = array_key_exists('delai_paiement_jours', $data)
                ? (int) $data['delai_paiement_jours']
                : (int) ($facture->delai_paiement_jours ?? 0);

            if (! in_array($delaiPaiementJours, ConditionsCommerciales::delaisPaiementJours(), true)) {
                throw ValidationException::withMessages([
                    'delai_paiement_jours' => 'Délai de paiement invalide.',
                ]);
            }

            $periodicite = array_key_exists('periodicite', $data)
                ? (filled($data['periodicite'] ?? null)
                    ? PeriodiciteFacturation::from((string) $data['periodicite'])
                    : null)
                : $facture->periodicite;

            $dateDebutService = filled($data['date_debut_service'] ?? null)
                ? (string) $data['date_debut_service']
                : ($facture->date_debut_service?->format('Y-m-d')
                    ?? $facture->periode_debut?->format('Y-m-d')
                    ?? $facture->date_emission->format('Y-m-d'));

            $dateFinService = array_key_exists('date_fin_service', $data)
                ? (filled($data['date_fin_service'] ?? null) ? (string) $data['date_fin_service'] : null)
                : $facture->date_fin_service?->format('Y-m-d');

            $periodeDebut = $facture->periode_debut?->format('Y-m-d');
            $periodeFin = $facture->periode_fin?->format('Y-m-d');
            if ($periodicite) {
                [$periodeDebut, $periodeFin] = ConditionsCommerciales::periodeFacturee(
                    $dateDebutService,
                    $periodicite,
                );
            }

            $abonnementId = array_key_exists('abonnement_id', $data)
                ? (filled($data['abonnement_id'] ?? null) ? (string) $data['abonnement_id'] : null)
                : $facture->abonnement_id;

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
            }

            $conditionsPaiement = filled($data['conditions_paiement'] ?? null)
                ? (string) $data['conditions_paiement']
                : ConditionsCommerciales::libelleDelaiPaiement($delaiPaiementJours);

            $notes = array_key_exists('notes', $data)
                ? ($data['notes'] ?? null)
                : $facture->notes;

            if ($periodicite && (blank($notes) || preg_match(
                "/^L'abonnement (mensuel|trimestriel|annuel) sera de [\\d\\s\\x{00a0}\\x{202f}]+ FCFA HT\\.$/u",
                (string) $notes
            ))) {
                $notes = ConditionsCommerciales::noteAbonnement($periodicite, $montantHt);
            }

            $facture->update([
                'client_id' => $client?->id,
                'abonnement_id' => $abonnementId,
                'site_id' => array_key_exists('site_id', $data)
                    ? ($client && filled($data['site_id'] ?? null) ? $data['site_id'] : null)
                    : ($client ? $facture->site_id : null),
                'date_echeance' => ConditionsCommerciales::dateEcheance(
                    $facture->date_emission->format('Y-m-d'),
                    $delaiPaiementJours,
                ),
                'periode_debut' => $periodeDebut,
                'periode_fin' => $periodeFin,
                'periodicite' => $periodicite?->value,
                'date_debut_service' => $dateDebutService,
                'date_fin_service' => $dateFinService,
                'montant_ht' => $montantHt,
                'montant_tva' => $montantTva,
                'montant_ttc' => $montantTtc,
                'taux_tva' => $tauxTva,
                'client_nom' => $client?->raison_sociale ?? $clientNom,
                'client_adresse' => $client?->adresse
                    ?? (array_key_exists('client_adresse', $data)
                        ? (filled($data['client_adresse'] ?? null) ? (string) $data['client_adresse'] : null)
                        : $facture->client_adresse),
                'client_telephone' => $client?->telephone
                    ?? (array_key_exists('client_telephone', $data)
                        ? (filled($data['client_telephone'] ?? null) ? (string) $data['client_telephone'] : null)
                        : $facture->client_telephone),
                'client_email' => $client?->email
                    ?? (array_key_exists('client_email', $data)
                        ? (filled($data['client_email'] ?? null) ? (string) $data['client_email'] : null)
                        : $facture->client_email),
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

            $facture->lignes()->delete();
            foreach ($prepared as $ligne) {
                $facture->lignes()->create($ligne);
            }

            GenererFacturePdfJob::dispatchSync($facture->id);

            return $facture->fresh()->load(['lignes', 'client', 'abonnement', 'media']);
        });
    }
}
