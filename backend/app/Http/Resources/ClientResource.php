<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
        'id' => $this->id,
        'type' => $this->type,
        'raison_sociale' => $this->raison_sociale,
        'nom_responsable' => $this->nom_responsable,
        'personne_contact' => $this->personne_contact,
        'telephone' => $this->telephone,
        'email' => $this->email,
        'adresse' => $this->adresse,
        'statut' => $this->statut,
        'sites_count' => $this->whenCounted('sites'),
        'created_at' => $this->created_at,
        ];
    }
}
