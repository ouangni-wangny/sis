<?php

namespace App\Http\Resources;

use App\Domain\Paie\SituationSalaireMois;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AvanceSalaireResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $situation = $this->agent
            ? SituationSalaireMois::pour($this->agent, $this->mois, $this->annee)
            : null;

        return [
            'id' => $this->id,
            'agent_id' => $this->agent_id,
            'mois' => $this->mois,
            'annee' => $this->annee,
            'montant' => $this->montant,
            'date_avance' => $this->date_avance?->toDateString(),
            'compte_tresorerie_id' => $this->compte_tresorerie_id,
            'mode' => $this->mode,
            'reference' => $this->reference,
            'notes' => $this->notes,
            'statut' => $this->statut,
            'bulletin_paie_id' => $this->bulletin_paie_id,
            'annulee_le' => $this->annulee_le,
            'motif_annulation' => $this->motif_annulation,
            'situation' => $situation,
            'agent' => $this->whenLoaded('agent', fn () => $this->agent ? [
                'id' => $this->agent->id,
                'nom' => $this->agent->nom,
                'prenom' => $this->agent->prenom,
                'matricule' => $this->agent->matricule,
            ] : null),
            'compte' => $this->whenLoaded('compte', fn () => $this->compte ? [
                'id' => $this->compte->id,
                'libelle' => $this->compte->libelle,
            ] : null),
        ];
    }
}
