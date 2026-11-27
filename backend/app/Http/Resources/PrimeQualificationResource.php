<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PrimeQualificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'qualification_id' => $this->qualification_id,
            'qualification' => $this->whenLoaded('qualification', fn () => [
                'id' => $this->qualification?->id,
                'libelle' => $this->qualification?->libelle,
                'abreviation' => $this->qualification?->abreviation,
            ]),
            'montant' => (float) $this->montant,
            'unite' => $this->unite?->value,
            'actif' => (bool) $this->actif,
        ];
    }
}
