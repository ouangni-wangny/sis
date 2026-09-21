<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\CheckpointResource;
use App\Http\Resources\AgentResource;
use App\Http\Resources\SiteResource;
use App\Http\Resources\RondeCheckpointResource;

class RondeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
        'id' => $this->id,
        'agent_id' => $this->agent_id,
        'site_id' => $this->site_id,
        'vacation_id' => $this->vacation_id,
        'demarree_at' => $this->demarree_at,
        'terminee_at' => $this->terminee_at,
        'statut' => $this->statut,
        'progression' => $this->progression,
        'agent' => new AgentResource($this->whenLoaded('agent')),
        'site' => new SiteResource($this->whenLoaded('site')),
        'ronde_checkpoints' => RondeCheckpointResource::collection($this->whenLoaded('rondeCheckpoints')),
        ];
    }
}
