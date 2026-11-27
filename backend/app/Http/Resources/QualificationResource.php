<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QualificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'libelle' => $this->libelle,
            'abreviation' => $this->abreviation,
            'description' => $this->description,
            'actif' => (bool) $this->actif,
            'primes_count' => $this->whenCounted('primes'),
        ];
    }
}
