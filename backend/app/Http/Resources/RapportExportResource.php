<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RapportExportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
        'id' => $this->id,
        'type' => $this->type,
        'format' => $this->format,
        'statut' => $this->statut,
        'filtres' => $this->filtres,
        'erreur' => $this->erreur,
        'export' => $this->when(
            $this->relationLoaded('media'),
            function () {
                $url = $this->getFirstMediaUrl('export');

                return $url !== '' ? ['url' => $url] : null;
            },
        ),
        'created_at' => $this->created_at,
        ];
    }
}
