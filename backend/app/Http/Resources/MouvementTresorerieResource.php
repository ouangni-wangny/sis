<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MouvementTresorerieResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'compte_tresorerie_id' => $this->compte_tresorerie_id,
            'direction' => $this->direction,
            'montant' => $this->montant,
            'date_mouvement' => $this->date_mouvement,
            'mode' => $this->mode,
            'source_type' => $this->source_type,
            'source_id' => $this->source_id,
            'reference' => $this->reference,
            'notes' => $this->notes,
            'user_id' => $this->user_id,
            'created_at' => $this->created_at,
            'compte' => $this->whenLoaded('compte', fn () => $this->compte ? [
                'id' => $this->compte->id,
                'libelle' => $this->compte->libelle,
                'type' => $this->compte->type,
            ] : null),
        ];
    }
}
