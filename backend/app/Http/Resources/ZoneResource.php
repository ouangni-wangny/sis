<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ZoneResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nom' => $this->nom,
            'description' => $this->description,
            'sites_count' => $this->whenCounted('sites'),
            'controleurs_count' => $this->whenCounted('controleurs'),
            'controleurs' => $this->whenLoaded('controleurs', function () {
                return $this->controleurs->map(fn ($agent) => [
                    'id' => $agent->id,
                    'nom' => $agent->nom,
                    'prenom' => $agent->prenom,
                    'matricule' => $agent->matricule,
                    'statut' => $agent->statut?->value ?? $agent->statut,
                    'indice_releve' => $agent->pivot->indice_releve,
                    'releve_depuis' => $agent->pivot->releve_depuis,
                    'releve_jusque' => $agent->pivot->releve_jusque,
                ]);
            }),
            'created_at' => $this->created_at,
        ];
    }
}
