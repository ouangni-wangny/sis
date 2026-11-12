<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanningSemaineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'poste_id' => $this->poste_id,
            'annee_iso' => $this->annee_iso,
            'semaine_iso' => $this->semaine_iso,
            'statut' => $this->statut instanceof \BackedEnum
                ? $this->statut->value
                : $this->statut,
            'date_lundi' => $this->lundi()->toDateString(),
            'date_dimanche' => $this->dimanche()->toDateString(),
            'creneaux' => $this->whenLoaded('creneaux', fn () => $this->creneaux->map(fn ($c) => [
                'id' => $c->id,
                'agent_id' => $c->agent_id,
                'jour' => $c->jour,
                'heure_debut' => $c->heureDebutHm(),
                'heure_fin' => $c->heureFinHm(),
                'quart' => $c->quart,
                'agent' => $c->relationLoaded('agent') && $c->agent ? [
                    'id' => $c->agent->id,
                    'nom' => $c->agent->nom,
                    'prenom' => $c->agent->prenom,
                    'matricule' => $c->agent->matricule,
                ] : null,
            ])->values()),
            'poste' => $this->whenLoaded('poste', fn () => [
                'id' => $this->poste->id,
                'nom' => $this->poste->nom,
                'site_id' => $this->poste->site_id,
                'site' => $this->poste->relationLoaded('site') && $this->poste->site ? [
                    'id' => $this->poste->site->id,
                    'nom' => $this->poste->site->nom,
                ] : null,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
