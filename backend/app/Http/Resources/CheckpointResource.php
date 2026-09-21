<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CheckpointResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
        'id' => $this->id,
        'site_id' => $this->site_id,
        'nom' => $this->nom,
        'code_qr' => $this->code_qr,
        'latitude' => $this->latitude,
        'longitude' => $this->longitude,
        'ordre' => $this->ordre,
        ];
    }
}
