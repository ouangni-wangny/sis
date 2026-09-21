<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\ZoneResource;

class RondierPerimetreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'agent_id' => $this->agent_id,
            'zone_id' => $this->zone_id,
            'indice_releve' => $this->indice_releve,
            'releve_depuis' => $this->releve_depuis?->format('Y-m-d'),
            'releve_jusque' => $this->releve_jusque?->format('Y-m-d'),
            'zone' => new ZoneResource($this->whenLoaded('zone')),
        ];
    }
}
