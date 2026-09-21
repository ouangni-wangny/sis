<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PosteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'site_id' => $this->site_id,
            'nom' => $this->nom,
            'agents_requis' => $this->agents_requis,
            'mode_effectif' => $this->mode_effectif instanceof \BackedEnum
                ? $this->mode_effectif->value
                : ($this->mode_effectif ?? 'ensemble'),
            'heure_debut' => $this->heure_debut,
            'heure_fin' => $this->heure_fin,
            'heure_debut_nuit' => $this->heure_debut_nuit,
            'heure_fin_nuit' => $this->heure_fin_nuit,
            'site' => $this->whenLoaded('site', fn () => [
                'id' => $this->site->id,
                'nom' => $this->site->nom,
            ]),
        ];
    }
}
