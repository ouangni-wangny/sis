<?php

namespace App\Application\Commercial;

use App\Domain\Commercial\ConditionsCommerciales;
use App\Domain\Shared\Enums\PeriodiciteFacturation;
use App\Domain\Shared\Enums\StatutAbonnement;
use App\Domain\Shared\Enums\StatutClient;
use App\Domain\Shared\Enums\StatutFacture;
use App\Domain\Shared\Enums\TypeClient;
use App\Models\Client;
use App\Models\Facture;
use App\Models\Offre;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * À l’acceptation du devis : client (si besoin) + un abonnement pour le combiné.
 */
final class CreateAbonnementsFromProformaAction
{
    public function __construct(
        private readonly UpsertAbonnementAction $upsertAbonnement,
    ) {}

    public function execute(Facture $facture): Facture
    {
        return DB::transaction(function () use ($facture) {
            $facture->loadMissing(['lignes', 'client']);

            if ($facture->statut !== StatutFacture::Valide) {
                return $facture;
            }

            if ($facture->abonnement_id) {
                return $facture;
            }

            $periodicite = $facture->periodicite;
            if (! $periodicite instanceof PeriodiciteFacturation) {
                throw ValidationException::withMessages([
                    'periodicite' => 'Indiquez une périodicité sur la proforma avant de la valider.',
                ]);
            }

            if (! $facture->client_id) {
                $this->creerClientDepuisProforma($facture);
                $facture->refresh()->loadMissing(['lignes', 'client']);
            }

            if (! $facture->client_id) {
                throw ValidationException::withMessages([
                    'client_id' => 'Impossible de valider : aucun client à lier.',
                ]);
            }

            $offreIds = $facture->lignes
                ->pluck('offre_id')
                ->filter()
                ->unique()
                ->values()
                ->all();

            if ($facture->lignes->isEmpty()) {
                return $facture;
            }

            $dateDebut = $facture->date_debut_service?->format('Y-m-d')
                ?? $facture->periode_debut?->format('Y-m-d')
                ?? $facture->date_emission?->format('Y-m-d')
                ?? now()->timezone('Africa/Abidjan')->toDateString();

            $dateFin = $facture->date_fin_service?->format('Y-m-d');

            $designation = $this->designationFromFacture($facture, $offreIds);
            $offreId = count($offreIds) === 1 ? $offreIds[0] : null;

            $abonnement = $this->upsertAbonnement->create([
                'client_id' => $facture->client_id,
                'offre_id' => $offreId,
                'designation' => $designation,
                'site_id' => $facture->site_id,
                'periodicite' => $periodicite->value,
                'date_debut' => $dateDebut,
                'date_fin' => $dateFin,
                // Première période déjà couverte par la proforma validée.
                'prochaine_facture_le' => ConditionsCommerciales::debutPeriodeSuivante(
                    $dateDebut,
                    $periodicite,
                ),
                'statut' => StatutAbonnement::Actif->value,
            ]);

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

            $facture->update([
                'abonnement_id' => $abonnement->id,
            ]);

            return $facture->fresh()->load(['lignes', 'client', 'abonnement.lignes', 'media']);
        });
    }

    private function creerClientDepuisProforma(Facture $facture): void
    {
        $nom = trim((string) $facture->client_nom);
        if ($nom === '') {
            throw ValidationException::withMessages([
                'client_nom' => 'Indiquez le nom du destinataire sur la proforma avant de valider (il servira à créer le client).',
            ]);
        }

        $client = Client::query()->create([
            'type' => TypeClient::Entreprise,
            'raison_sociale' => $nom,
            'telephone' => $facture->client_telephone,
            'email' => $facture->client_email,
            'adresse' => $facture->client_adresse,
            'statut' => StatutClient::Actif,
        ]);

        $facture->update([
            'client_id' => $client->id,
            'client_nom' => $client->raison_sociale,
            'client_adresse' => $client->adresse,
            'client_telephone' => $client->telephone,
            'client_email' => $client->email,
        ]);
    }

    /**
     * @param  list<string>  $offreIds
     */
    private function designationFromFacture(Facture $facture, array $offreIds): string
    {
        if ($offreIds !== []) {
            $libelles = Offre::query()
                ->whereIn('id', $offreIds)
                ->orderBy('libelle')
                ->pluck('libelle')
                ->all();

            if ($libelles !== []) {
                return implode(' + ', $libelles);
            }
        }

        $fromLignes = $facture->lignes
            ->pluck('description')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($fromLignes !== []) {
            return implode(' + ', $fromLignes);
        }

        return 'Abonnement '.$facture->numero;
    }
}
