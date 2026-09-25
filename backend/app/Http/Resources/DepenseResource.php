<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DepenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'categorie_depense_id' => $this->categorie_depense_id,
            'libelle' => $this->libelle,
            'montant' => $this->montant,
            'date_depense' => $this->date_depense,
            'compte_tresorerie_id' => $this->compte_tresorerie_id,
            'mode' => $this->mode,
            'reference' => $this->reference,
            'notes' => $this->notes,
            'user_id' => $this->user_id,
            'created_at' => $this->created_at,
            'categorie' => $this->whenLoaded('categorie', fn () => $this->categorie ? [
                'id' => $this->categorie->id,
                'libelle' => $this->categorie->libelle,
            ] : null),
            'compte' => $this->whenLoaded('compte', fn () => $this->compte ? [
                'id' => $this->compte->id,
                'libelle' => $this->compte->libelle,
                'type' => $this->compte->type,
            ] : null),
            'has_justificatif' => $this->when(
                $this->relationLoaded('media'),
                fn () => (bool) $this->getFirstMedia('justificatif'),
            ),
        ];
    }
}
