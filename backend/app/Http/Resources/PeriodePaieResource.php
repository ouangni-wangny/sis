<?php

namespace App\Http\Resources;

use App\Support\RhAuthorization;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PeriodePaieResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'mois' => $this->mois,
            'annee' => $this->annee,
            'date_debut' => $this->date_debut?->toDateString(),
            'date_fin' => $this->date_fin?->toDateString(),
            'statut' => $this->statut,
            'commentaire' => $this->commentaire,
            'bulletins_count' => $this->whenCounted('bulletins'),
        ];
    }
}
