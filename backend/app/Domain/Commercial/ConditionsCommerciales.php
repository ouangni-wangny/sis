<?php

namespace App\Domain\Commercial;

use App\Domain\Shared\Enums\PeriodiciteFacturation;
use Carbon\Carbon;

final class ConditionsCommerciales
{
    /** @return list<int> */
    public static function delaisPaiementJours(): array
    {
        return [0, 15, 30, 45, 60];
    }

    public static function libelleDelaiPaiement(int $jours): string
    {
        return match ($jours) {
            0 => 'Paiement au comptant avant la mise en place',
            15 => 'Paiement à 15 jours net',
            30 => 'Paiement à 30 jours net',
            45 => 'Paiement à 45 jours net',
            60 => 'Paiement à 60 jours net',
            default => sprintf('Paiement à %d jours net', $jours),
        };
    }

    public static function dateEcheance(string $dateEmission, int $delaiJours): string
    {
        $date = Carbon::parse($dateEmission)->timezone('Africa/Abidjan')->startOfDay();

        if ($delaiJours <= 0) {
            return $date->toDateString();
        }

        return $date->addDays($delaiJours)->toDateString();
    }

    /**
     * @return array{0: string, 1: string} [debut, fin]
     */
    public static function periodeFacturee(
        string $debut,
        PeriodiciteFacturation $periodicite,
    ): array {
        $start = Carbon::parse($debut)->timezone('Africa/Abidjan')->startOfDay();
        $end = $start->copy()->addMonthsNoOverflow($periodicite->mois())->subDay();

        return [$start->toDateString(), $end->toDateString()];
    }

    /** Début de la période suivante (facturation d’avance). */
    public static function debutPeriodeSuivante(
        string $debutPeriode,
        PeriodiciteFacturation $periodicite,
    ): string {
        return Carbon::parse($debutPeriode)
            ->timezone('Africa/Abidjan')
            ->startOfDay()
            ->addMonthsNoOverflow($periodicite->mois())
            ->toDateString();
    }

    public static function noteAbonnement(
        PeriodiciteFacturation $periodicite,
        float|int|string $montantHt,
    ): string {
        $montant = number_format((float) $montantHt, 0, ',', ' ');

        return sprintf(
            "L'abonnement %s sera de %s FCFA HT.",
            $periodicite->label(),
            $montant
        );
    }
}
