<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\PosteResource;
use App\Http\Resources\AgentResource;
use App\Http\Resources\SiteResource;

class VacationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
        'id' => $this->id,
        'agent_id' => $this->agent_id,
        'site_id' => $this->site_id,
        'poste_id' => $this->poste_id,
        'date_debut' => $this->date_debut,
        'date_fin' => $this->date_fin,
        'heure_debut' => $this->heure_debut,
        'heure_fin' => $this->heure_fin,
        'statut' => $this->statut,
        'agent' => new AgentResource($this->whenLoaded('agent')),
        'site' => new SiteResource($this->whenLoaded('site')),
        'poste' => new PosteResource($this->whenLoaded('poste')),
        ];
    }
}
