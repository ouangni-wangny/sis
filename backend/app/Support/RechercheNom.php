<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class RechercheNom
{
    /**
     * Ajoute « OU tous les mots saisis figurent dans le nom ou le prénom », dans n’importe
     * quel ordre : « Richard Koffi » trouve prénom « Kouame Richard », nom « Koffi ».
     * Un seul mot est déjà couvert par les conditions sur nom et prénom.
     *
     * @param  Builder<Model>  $query
     * @param  string  $term  Terme de recherche, avec ou sans ses jokers %.
     */
    public static function ouNomComplet(Builder $query, string $term): void
    {
        $mots = preg_split('/\s+/u', trim($term, "% \t\n\r"), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($mots) < 2) {
            return;
        }

        $query->orWhere(function (Builder $tous) use ($mots) {
            foreach ($mots as $mot) {
                $tous->where(function (Builder $unMot) use ($mot) {
                    $unMot->where('nom', 'like', "%{$mot}%")
                        ->orWhere('prenom', 'like', "%{$mot}%");
                });
            }
        });
    }
}
