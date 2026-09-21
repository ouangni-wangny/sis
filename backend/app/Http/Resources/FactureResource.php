<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FactureResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'abonnement_id' => $this->abonnement_id,
            'site_id' => $this->site_id,
            'numero' => $this->numero,
            'date_emission' => $this->date_emission,
            'date_echeance' => $this->date_echeance,
            'periode_debut' => $this->periode_debut,
            'periode_fin' => $this->periode_fin,
            'periodicite' => $this->periodicite,
            'date_debut_service' => $this->date_debut_service,
            'date_fin_service' => $this->date_fin_service,
            'montant_ht' => $this->montant_ht,
            'montant_tva' => $this->montant_tva,
            'montant_ttc' => $this->montant_ttc,
            'montant_paye' => $this->montantPaye(),
            'solde' => $this->solde(),
            'statut_paiement' => $this->statutPaiement(),
            'devise' => $this->devise,
            'statut' => $this->statut,
            'lieu_emission' => $this->lieu_emission,
            'affaire_suivie_par' => $this->affaire_suivie_par,
            'telephone_commercial' => $this->telephone_commercial,
            'taux_tva' => $this->taux_tva,
            'client_nom' => $this->client_nom,
            'client_adresse' => $this->client_adresse,
            'client_telephone' => $this->client_telephone,
            'client_email' => $this->client_email,
            'notes' => $this->notes,
            'conditions_paiement' => $this->conditions_paiement,
            'delai_paiement_jours' => $this->delai_paiement_jours,
            'delai_validite' => $this->delai_validite,
            'duree_contrat_min' => $this->duree_contrat_min,
            'signataire_nom' => $this->signataire_nom,
            'signataire_fonction' => $this->signataire_fonction,
            'montant_ttc_lettres' => $this->montant_ttc_lettres,
            'client' => new ClientResource($this->whenLoaded('client')),
            'abonnement' => $this->whenLoaded('abonnement', fn () => $this->abonnement ? [
                'id' => $this->abonnement->id,
                'periodicite' => $this->abonnement->periodicite,
                'date_debut' => $this->abonnement->date_debut,
                'date_fin' => $this->abonnement->date_fin,
                'statut' => $this->abonnement->statut,
                'designation' => $this->abonnement->designation,
                'offre_id' => $this->abonnement->offre_id,
            ] : null),
            'lignes' => LigneFactureResource::collection($this->whenLoaded('lignes')),
            'pdf' => $this->when(
                $this->relationLoaded('media'),
                function () {
                    $url = $this->getFirstMediaUrl('pdf');

                    return $url !== '' ? ['url' => $url] : null;
                },
            ),
        ];
    }
}
