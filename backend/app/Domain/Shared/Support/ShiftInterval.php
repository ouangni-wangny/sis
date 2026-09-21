<?php

namespace App\Domain\Shared\Support;

use Carbon\Carbon;

/**
 * Source unique — intervalles horaires (jour / nuit / cycle 24h).
 *
 * Règles fixées (alignées sur frontend/src/domain/time/shift-interval.ts) :
 * - overnight = fin < début (strict) — traverse minuit
 * - cycle 24h = début === fin (non vide) — relève → relève
 * - span cycle / overnight = +24h
 */
final class ShiftInterval
{
    public static function hm(string $time): string
    {
        return Carbon::parse($time)->format('H:i');
    }

    /** Traverse minuit (ex. 18:30→06:30). début === fin n’est PAS overnight. */
    public static function isOvernight(string $heureDebut, string $heureFin): bool
    {
        return self::hm($heureFin) < self::hm($heureDebut);
    }

    /** Cycle 24h continu (ex. 06:30→06:30). */
    public static function isCycle24h(string $heureDebut, string $heureFin): bool
    {
        $d = self::hm($heureDebut);
        $f = self::hm($heureFin);

        return $d !== '' && $f !== '' && $d === $f;
    }

    /** Overnight ou cycle → date de fin calendaire au lendemain si besoin. */
    public static function needsNextCalendarDay(string $heureDebut, string $heureFin): bool
    {
        return self::isOvernight($heureDebut, $heureFin)
            || self::isCycle24h($heureDebut, $heureFin);
    }

    public static function dateFinForShift(
        string $dateDebut,
        string $heureDebut,
        string $heureFin,
        ?string $dateFin = null,
    ): ?string {
        $dateDebut = Carbon::parse($dateDebut)->toDateString();
        $dateFin = $dateFin !== null && $dateFin !== ''
            ? Carbon::parse($dateFin)->toDateString()
            : null;

        if (self::needsNextCalendarDay($heureDebut, $heureFin)) {
            if ($dateFin === null || $dateFin <= $dateDebut) {
                return Carbon::parse($dateDebut)->addDay()->toDateString();
            }
        }

        return $dateFin;
    }

    /** Durée en heures (cycle / overnight → +24h). */
    public static function durationHours(string $heureDebut, string $heureFin): float
    {
        $start = Carbon::parse(self::hm($heureDebut));
        $end = Carbon::parse(self::hm($heureFin));

        if ($end->lessThanOrEqualTo($start)) {
            $end = $end->copy()->addDay();
        }

        return $start->diffInMinutes($end) / 60;
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function range(
        string $dateDebut,
        string $heureDebut,
        string $heureFin,
        ?string $dateFin = null,
    ): array {
        $heureDebut = self::hm($heureDebut);
        $heureFin = self::hm($heureFin);
        $start = Carbon::parse("{$dateDebut} {$heureDebut}");
        $endDate = self::dateFinForShift($dateDebut, $heureDebut, $heureFin, $dateFin)
            ?? Carbon::parse($dateDebut)->toDateString();

        return [$start, Carbon::parse("{$endDate} {$heureFin}")];
    }

    /**
     * Demi-journées logiques Jour (diurne) / Nuit (overnight) d’un cycle 24h.
     *
     * @return array{jour: array{heure_debut: string, heure_fin: string}, nuit: array{heure_debut: string, heure_fin: string}}|null
     */
    public static function dayNightHalvesFromCycle(string $releve): ?array
    {
        $t = self::hm($releve);
        if ($t === '') {
            return null;
        }

        $start = Carbon::parse($t);
        $mid = $start->copy()->addHours(12);
        $midHm = $mid->format('H:i');

        $premiere = ['heure_debut' => $t, 'heure_fin' => $midHm];
        $seconde = ['heure_debut' => $midHm, 'heure_fin' => $t];

        if (self::isOvernight($premiere['heure_debut'], $premiere['heure_fin'])) {
            return ['nuit' => $premiere, 'jour' => $seconde];
        }

        return ['jour' => $premiere, 'nuit' => $seconde];
    }
}
