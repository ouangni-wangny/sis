<?php

namespace App\Http\Resources;

use App\Support\RhAuthorization;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BulletinPaieResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $canSeeSalaire = RhAuthorization::canSeeSalaire($request->user());

        return [
            'id' => $this->id,
            'periode_paie_id' => $this->periode_paie_id,
            'agent_id' => $this->agent_id,
            'contrat_id' => $this->contrat_id,
            'salaire_brut' => $canSeeSalaire ? $this->salaire_brut : null,
            'retenue_cnps' => $canSeeSalaire ? $this->retenue_cnps : null,
            'montant_igr' => $canSeeSalaire ? $this->montant_igr : null,
            'salaire_net' => $canSeeSalaire ? $this->salaire_net : null,
            'statut' => $this->statut,
            'paye_le' => $this->paye_le,
            'details' => $canSeeSalaire ? $this->details : null,
            'pdf_url' => $this->when(
                $this->relationLoaded('media') && $this->getFirstMedia('pdf'),
                fn () => url("/api/v1/bulletins-paie/{$this->id}/pdf"),
            ),
            'agent' => $this->whenLoaded('agent', fn () => [
                'id' => $this->agent->id,
                'nom' => $this->agent->nom,
                'prenom' => $this->agent->prenom,
                'matricule' => $this->agent->matricule,
            ]),
            'periode_paie' => $this->whenLoaded('periodePaie', fn () => [
                'mois' => $this->periodePaie->mois,
                'annee' => $this->periodePaie->annee,
            ]),
        ];
    }
}
