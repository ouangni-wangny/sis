<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnomalieResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
        'id' => $this->id,
        'client_uuid' => $this->client_uuid,
        'signale_par_id' => $this->signale_par_id,
        'assigne_a_id' => $this->assigne_a_id,
        'site_id' => $this->site_id,
        'type' => $this->type,
        'gravite' => $this->gravite,
        'statut' => $this->statut,
        'commentaire' => $this->commentaire,
        'signale_at' => $this->signale_at,
        'resolue_at' => $this->resolue_at,
        'site' => new SiteResource($this->whenLoaded('site')),
        'photos' => $this->whenLoaded('media', fn () => $this->getMedia('photos')->map(fn ($m) => [
            'url' => $m->getUrl(),
        ])),
        ];
    }
}
