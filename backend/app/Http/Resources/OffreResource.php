<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OffreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
        'id' => $this->id,
        'libelle' => $this->libelle,
        'description' => $this->description,
        'prix_mensuel' => $this->prix_mensuel,
        'actif' => $this->actif,
        ];
    }
}
