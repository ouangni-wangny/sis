<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompteTresorerieResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'libelle' => $this->libelle,
            'type' => $this->type,
            'solde_ouverture' => $this->solde_ouverture,
            'actif' => $this->actif,
            'solde' => $this->when(
                $request->boolean('with_solde', true),
                fn () => $this->soldeCourant(),
            ),
            'created_at' => $this->created_at,
        ];
    }
}
