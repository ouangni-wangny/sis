<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SiteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nom' => $this->nom,
            'adresse' => $this->adresse,
            'responsable' => $this->responsable,
            'tarif_mensuel' => $this->tarif_mensuel,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'rayon_metres' => $this->rayon_metres,
            'interne' => (bool) $this->interne,
            'client_id' => $this->client_id,
            'zone_id' => $this->zone_id,
            'client' => new ClientResource($this->whenLoaded('client')),
            'zone' => new ZoneResource($this->whenLoaded('zone')),
            'postes' => PosteResource::collection($this->whenLoaded('postes')),
            'checkpoints' => CheckpointResource::collection($this->whenLoaded('checkpoints')),
        ];
    }
}
