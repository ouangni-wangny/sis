<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AbonnementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'offre_id' => $this->offre_id,
            'designation' => $this->designation,
            'site_id' => $this->site_id,
            'periodicite' => $this->periodicite,
            'date_debut' => $this->date_debut,
            'date_fin' => $this->date_fin,
            'prochaine_facture_le' => $this->prochaine_facture_le,
            'statut' => $this->statut,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'client' => $this->whenLoaded('client', fn () => $this->client ? [
                'id' => $this->client->id,
                'raison_sociale' => $this->client->raison_sociale,
                'type' => $this->client->type,
                'personne_contact' => $this->client->personne_contact,
                'telephone' => $this->client->telephone,
                'email' => $this->client->email,
                'adresse' => $this->client->adresse,
                'statut' => $this->client->statut,
            ] : null),
            'offre' => $this->whenLoaded('offre', fn () => $this->offre ? [
                'id' => $this->offre->id,
                'libelle' => $this->offre->libelle,
                'prix_mensuel' => $this->offre->prix_mensuel,
            ] : null),
            'site' => $this->whenLoaded('site', fn () => $this->site ? [
                'id' => $this->site->id,
                'nom' => $this->site->nom,
                'adresse' => $this->site->adresse,
            ] : null),
            'lignes' => $this->whenLoaded('lignes', fn () => $this->lignes->map(fn ($l) => [
                'id' => $l->id,
                'offre_id' => $l->offre_id,
                'description' => $l->description,
                'quantite' => $l->quantite,
                'prix_unitaire' => $l->prix_unitaire,
                'montant' => $l->montant,
                'ordre' => $l->ordre,
                'offre' => $l->relationLoaded('offre') && $l->offre ? [
                    'id' => $l->offre->id,
                    'libelle' => $l->offre->libelle,
                ] : null,
            ])),
            'montant_ht' => $this->whenLoaded('lignes', fn () => round((float) $this->lignes->sum('montant'), 2)),
            'factures' => $this->whenLoaded('factures', fn () => $this->factures->map(fn ($f) => [
                'id' => $f->id,
                'numero' => $f->numero,
                'date_emission' => $f->date_emission,
                'montant_ht' => $f->montant_ht,
                'montant_ttc' => $f->montant_ttc,
                'statut' => $f->statut,
                'periodicite' => $f->periodicite,
            ])),
        ];
    }
}
