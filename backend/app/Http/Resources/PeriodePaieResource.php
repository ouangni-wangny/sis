<?php

namespace App\Http\Resources;

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
            // Masse nette du mois : visible dès qu’on peut consulter la paie (RH, commercial, comptable…).
            // Les montants individuels restent masqués hors contrats.manage.
            'masse_salariale' => $this->when(
                array_key_exists('masse_salariale', $this->resource->getAttributes())
                    || isset($this->masse_salariale),
                fn () => round((float) ($this->masse_salariale ?? 0), 2),
            ),
        ];
    }
}
