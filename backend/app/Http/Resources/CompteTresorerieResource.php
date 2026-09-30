<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompteTresorerieResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Options formulaires : pas de soldes (RH / commercial).
        $withSolde = $request->boolean('with_solde', true);

        return [
            'id' => $this->id,
            'libelle' => $this->libelle,
            'type' => $this->type,
            'actif' => $this->actif,
            'solde_ouverture' => $this->when($withSolde, $this->solde_ouverture),
            'solde' => $this->when($withSolde, fn () => $this->soldeCourant()),
            'created_at' => $this->when($withSolde, $this->created_at),
        ];
    }
}
