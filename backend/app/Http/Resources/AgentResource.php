<?php

namespace App\Http\Resources;

use App\Support\RhAuthorization;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AgentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'grade_id' => $this->grade_id,
            'type' => $this->type,
            'nom' => $this->nom,
            'prenom' => $this->prenom,
            'civilite' => $this->civilite,
            'date_naissance' => $this->date_naissance?->toDateString(),
            'lieu_naissance' => $this->lieu_naissance,
            'situation_matrimoniale' => $this->situation_matrimoniale,
            'nombre_enfants' => $this->nombre_enfants,
            'nationalite' => $this->nationalite,
            'telephone' => $this->telephone,
            'numero_cni' => $this->numero_cni,
            'ville_id' => $this->ville_id,
            'ville' => $this->relationLoaded('villeRef') && $this->villeRef
                ? $this->villeRef->libelle
                : $this->ville,
            'domicile' => $this->domicile,
            'matricule' => $this->matricule,
            'cnps' => $this->cnps,
            'date_embauche' => $this->date_embauche?->toDateString(),
            'date_expiration_permis' => $this->date_expiration_permis?->toDateString(),
            'statut' => $this->statut,
            'pool_siege' => (bool) $this->pool_siege,
            'poste_siege_id' => $this->poste_siege_id,
            'poste_siege' => $this->whenLoaded('posteSiege', fn () => $this->posteSiege ? [
                'id' => $this->posteSiege->id,
                'nom' => $this->posteSiege->nom,
                'site' => $this->posteSiege->relationLoaded('site') && $this->posteSiege->site ? [
                    'id' => $this->posteSiege->site->id,
                    'nom' => $this->posteSiege->site->nom,
                ] : null,
            ] : null),
            'jour_repos' => $this->jour_repos,
            'conges_acquis_annuel' => $this->conges_acquis_annuel,
            'solde_conges_jours' => $this->solde_conges_jours,
            'has_pin' => $this->relationLoaded('user')
                ? filled($this->user?->pin_hash)
                : ($this->user_id ? null : false),
            'email' => $this->when(
                $this->relationLoaded('user'),
                fn () => $this->user?->email,
            ),
            'plain_pin' => $this->when(isset($this->plain_pin), $this->plain_pin),
            'grade' => new GradeResource($this->whenLoaded('grade')),
            'ville_ref' => new VilleResource($this->whenLoaded('villeRef')),
            'perimetres' => RondierPerimetreResource::collection($this->whenLoaded('perimetres')),
            'perimetre_sites_count' => $this->when(
                isset($this->perimetre_sites_count),
                $this->perimetre_sites_count,
            ),
            'perimetre_agents_count' => $this->when(
                isset($this->perimetre_agents_count),
                $this->perimetre_agents_count,
            ),
            'contrat_actif' => $this->whenLoaded('contratActif', function () use ($request) {
                if (! $this->contratActif) {
                    return null;
                }

                return [
                    'id' => $this->contratActif->id,
                    'type' => $this->contratActif->type,
                    'reference' => $this->contratActif->reference,
                    'date_debut' => $this->contratActif->date_debut?->toDateString(),
                    'date_fin' => $this->contratActif->date_fin?->toDateString(),
                    'periode_essai_mois' => $this->contratActif->periode_essai_mois,
                    'salaire_brut' => RhAuthorization::canSeeSalaire($request->user())
                        ? $this->contratActif->salaire_brut
                        : null,
                    'salaire_net' => RhAuthorization::canSeeSalaire($request->user())
                        ? $this->contratActif->salaire_net
                        : null,
                    'salaire' => RhAuthorization::canSeeSalaire($request->user())
                        ? ($this->contratActif->salaire_brut ?? $this->contratActif->salaire)
                        : null,
                    'statut' => $this->contratActif->statut,
                ];
            }),
            'photo' => $this->whenLoaded('media', fn () => [
                'url' => $this->getFirstMediaUrl('photo'),
                'thumb' => $this->getFirstMediaUrl('photo', 'thumb'),
            ]),
            'documents' => $this->whenLoaded('media', fn () => [
                'piece_identite' => (bool) $this->getFirstMedia('piece_identite'),
                'permis' => (bool) $this->getFirstMedia('permis'),
            ]),
        ];
    }
}
