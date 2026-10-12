<?php

namespace App\Domain\Commercial;

use App\Domain\Shared\Enums\StatutFacture;

/**
 * Mentions du PDF selon la nature du document.
 *
 * - Proforma (en attente) : « Facture pro forma », « Arrêté le présent devis »,
 *   NB abonnement mensuel et conditions de mise en place.
 * - Facture définitive (validée) : « Facture », « Arrêté la présente facture »,
 *   sans NB abonnement ni conditions de mise en place.
 */
final class MentionsFacture
{
    /**
     * @return array{definitive: bool, titre: string, arrete: string, afficher_nb: bool, afficher_conditions: bool}
     */
    public static function pour(StatutFacture|string|null $statut): array
    {
        $value = $statut instanceof StatutFacture ? $statut->value : (string) $statut;
        $definitive = $value === StatutFacture::Valide->value;

        return [
            'definitive' => $definitive,
            'titre' => $definitive ? 'Facture' : 'Facture pro forma',
            'arrete' => $definitive
                ? 'Arrêté la présente facture à la somme de'
                : 'Arrêté le présent devis à la somme de',
            'afficher_nb' => ! $definitive,
            'afficher_conditions' => ! $definitive,
        ];
    }
}
