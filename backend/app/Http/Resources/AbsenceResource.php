<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AbsenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'agent_id' => $this->agent_id,
            'type' => $this->type instanceof \BackedEnum
                ? $this->type->value
                : $this->type,
            'source' => $this->source instanceof \BackedEnum
                ? $this->source->value
                : ($this->source ?? 'rh'),
            'controle_id' => $this->controle_id,
            'date_debut' => $this->date_debut,
            'date_fin' => $this->date_fin,
            'motif' => $this->motif,
            'statut' => $this->statut instanceof \BackedEnum
                ? $this->statut->value
                : $this->statut,
            'vacations_marquees_a_recouvrir' => $this->when(
                $this->vacations_marquees_a_recouvrir !== null,
                (int) $this->vacations_marquees_a_recouvrir,
            ),
            'vacations_restaurees' => $this->when(
                $this->vacations_restaurees !== null,
                (int) $this->vacations_restaurees,
            ),
            'agent' => $this->whenLoaded('agent', fn () => [
                'id' => $this->agent->id,
                'nom' => $this->agent->nom,
                'prenom' => $this->agent->prenom,
                'matricule' => $this->agent->matricule,
            ]),
        ];
    }
}
