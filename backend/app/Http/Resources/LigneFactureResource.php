<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LigneFactureResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'offre_id' => $this->offre_id,
            'code_article' => $this->code_article,
            'description' => $this->description,
            'quantite' => $this->quantite,
            'prix_unitaire' => $this->prix_unitaire,
            'montant' => $this->montant,
            'ordre' => $this->ordre,
        ];
    }
}
