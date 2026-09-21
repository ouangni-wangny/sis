<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nom' => $this->nom,
            'prenom' => $this->prenom,
            'email' => $this->email,
            'matricule' => $this->matricule,
            'type' => $this->type,
            'statut' => $this->statut,
            'last_login_at' => $this->last_login_at,
            'roles' => $this->getRoleNames()->values(),
            'permissions' => $this->getAllPermissions()->pluck('name')->values(),
            'agent' => new AgentResource($this->whenLoaded('agent')),
            'created_at' => $this->created_at,
        ];
    }
}
