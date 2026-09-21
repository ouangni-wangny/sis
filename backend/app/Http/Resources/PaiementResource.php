<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaiementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'facture_id' => $this->facture_id,
            'montant' => $this->montant,
            'date_paiement' => $this->date_paiement,
            'mode' => $this->mode,
            'reference' => $this->reference,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'facture' => $this->whenLoaded('facture', fn () => $this->facture ? [
                'id' => $this->facture->id,
                'numero' => $this->facture->numero,
                'montant_ttc' => $this->facture->montant_ttc,
                'statut' => $this->facture->statut,
                'client_id' => $this->facture->client_id,
                'client' => $this->facture->relationLoaded('client') && $this->facture->client ? [
                    'id' => $this->facture->client->id,
                    'raison_sociale' => $this->facture->client->raison_sociale,
                ] : null,
            ] : null),
        ];
    }
}
