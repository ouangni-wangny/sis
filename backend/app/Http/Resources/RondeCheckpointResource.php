<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\CheckpointResource;

class RondeCheckpointResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
        'id' => $this->id,
        'ronde_id' => $this->ronde_id,
        'checkpoint_id' => $this->checkpoint_id,
        'scanne_at' => $this->scanne_at,
        'latitude' => $this->latitude,
        'longitude' => $this->longitude,
        'valide' => $this->valide,
        'checkpoint' => new CheckpointResource($this->whenLoaded('checkpoint')),
        ];
    }
}
