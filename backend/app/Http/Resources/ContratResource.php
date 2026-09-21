<?php

namespace App\Http\Resources;

use App\Support\RhAuthorization;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContratResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $canSeeSalaire = RhAuthorization::canSeeSalaire($request->user());

        return [
            'id' => $this->id,
            'agent_id' => $this->agent_id,
            'contrat_parent_id' => $this->contrat_parent_id,
            'type' => $this->type,
            'reference' => $this->reference,
            'date_debut' => $this->date_debut?->toDateString(),
            'date_fin' => $this->date_fin?->toDateString(),
            'duree_mois' => $this->duree_mois,
            'periode_essai_mois' => $this->periode_essai_mois,
            'salaire_base' => $canSeeSalaire ? $this->salaire_base : null,
            'indemnite_fonction' => $canSeeSalaire ? $this->indemnite_fonction : null,
            'prime_responsabilite' => $canSeeSalaire ? $this->prime_responsabilite : null,
            'prime_transport' => $canSeeSalaire ? $this->prime_transport : null,
            'prime_entretien_tenue' => $canSeeSalaire ? $this->prime_entretien_tenue : null,
            'sursalaire' => $canSeeSalaire ? $this->sursalaire : null,
            'nombre_enfants' => $this->nombre_enfants,
            'parts_igr' => $canSeeSalaire ? $this->parts_igr : null,
            'montant_igr' => $canSeeSalaire ? $this->montant_igr : null,
            'retenue_cnps' => $canSeeSalaire ? $this->retenue_cnps : null,
            'salaire_brut' => $canSeeSalaire ? $this->salaire_brut : null,
            'salaire_net' => $canSeeSalaire ? $this->salaire_net : null,
            'salaire' => $canSeeSalaire ? ($this->salaire_brut ?? $this->salaire) : null,
            'statut' => $this->statut,
            'document_url' => $this->when(
                $this->relationLoaded('media') && $this->getFirstMedia('document'),
                fn () => url("/api/v1/contrats/{$this->id}/document"),
            ),
            'agent' => $this->whenLoaded('agent', fn () => [
                'id' => $this->agent->id,
                'nom' => $this->agent->nom,
                'prenom' => $this->agent->prenom,
                'matricule' => $this->agent->matricule,
                'situation_matrimoniale' => $this->agent->situation_matrimoniale,
                'nombre_enfants' => $this->agent->nombre_enfants,
            ]),
            'contrat_parent' => $this->whenLoaded('contratParent', fn () => [
                'id' => $this->contratParent?->id,
                'reference' => $this->contratParent?->reference,
            ]),
        ];
    }
}
