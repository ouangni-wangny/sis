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
        $details = is_array($this->details) ? $this->details : [];
        $salaireRenseigne = array_key_exists('salaire_percu', $details)
            || array_key_exists('salaire_renseigne_le', $details);

        return [
            'id' => $this->id,
            'periode_paie_id' => $this->periode_paie_id,
            'agent_id' => $this->agent_id,
            'contrat_id' => $this->contrat_id,
            'salaire_brut' => $canSeeSalaire ? $this->salaire_brut : null,
            'retenue_cnps' => $canSeeSalaire ? $this->retenue_cnps : null,
            'montant_igr' => $canSeeSalaire ? $this->montant_igr : null,
            'salaire_net' => $canSeeSalaire ? $this->salaire_net : null,
            'salaire_renseigne' => $salaireRenseigne,
            'statut' => $this->statut,
            'paye_le' => $this->paye_le,
            'mode_paiement' => $this->mode_paiement,
            'compte_tresorerie_id' => $this->compte_tresorerie_id,
            'reference_paiement' => $this->reference_paiement,
            'details' => $canSeeSalaire ? $this->details : null,
            'pdf_url' => $this->when(
                $canSeeSalaire && $this->relationLoaded('media'),
                fn () => $this->getFirstMedia('pdf')
                    ? url("/api/v1/bulletins-paie/{$this->id}/pdf")
                    : null,
            ),
            'agent' => $this->whenLoaded('agent', fn () => [
                'id' => $this->agent->id,
                'nom' => $this->agent->nom,
                'prenom' => $this->agent->prenom,
                'matricule' => $this->agent->matricule,
                'telephone' => $this->agent->telephone,
                'grade' => $this->agent->relationLoaded('grade') && $this->agent->grade
                    ? [
                        'id' => $this->agent->grade->id,
                        'libelle' => $this->agent->grade->libelle,
                    ]
                    : null,
            ]),
            'periode_paie' => $this->whenLoaded('periodePaie', fn () => [
                'mois' => $this->periodePaie->mois,
                'annee' => $this->periodePaie->annee,
            ]),
            'compte_tresorerie' => $this->whenLoaded('compteTresorerie', fn () => $this->compteTresorerie ? [
                'id' => $this->compteTresorerie->id,
                'libelle' => $this->compteTresorerie->libelle,
                'type' => $this->compteTresorerie->type,
            ] : null),
        ];
    }
}
