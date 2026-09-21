<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ControleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_uuid' => $this->client_uuid,
            'agent_id' => $this->agent_id,
            'enregistre_par_user_id' => $this->enregistre_par_user_id,
            'site_id' => $this->site_id,
            'poste_id' => $this->poste_id,
            'controle_agent_id' => $this->controle_agent_id,
            'ronde_id' => $this->ronde_id,
            'effectue_at' => $this->effectue_at,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'commentaire' => $this->commentaire,
            'resultat' => $this->resultat?->value ?? 'enregistre',
            'agent' => new AgentResource($this->whenLoaded('agent')),
            'enregistre_par' => $this->whenLoaded('enregistrePar', function () {
                if (! $this->enregistrePar) {
                    return null;
                }

                return [
                    'id' => $this->enregistrePar->id,
                    'nom' => $this->enregistrePar->nom,
                    'prenom' => $this->enregistrePar->prenom,
                    'email' => $this->enregistrePar->email,
                    'full_name' => $this->enregistrePar->full_name,
                ];
            }),
            'controle_agent' => new AgentResource($this->whenLoaded('controleAgent')),
            'site' => new SiteResource($this->whenLoaded('site')),
            'poste' => new PosteResource($this->whenLoaded('poste')),
            'photos' => $this->whenLoaded('media', fn () => $this->getMedia('photos')->map(fn ($m) => [
                'url' => $m->getUrl(),
                'thumb' => $m->getUrl('thumb'),
            ])),
        ];
    }
}
