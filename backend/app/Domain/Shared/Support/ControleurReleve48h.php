<?php

namespace App\Domain\Shared\Support;

use Carbon\Carbon;

/**
 * Relève 48h des contrôleurs (2 jours en service / 2 jours repos).
 * Indice 0 : jours 0–1, 4–5… · Indice 1 : jours 2–3, 6–7…
 */
final class ControleurReleve48h
{
    public const BLOC_JOURS = 2;

    public const MAX_PAR_ZONE = 2;

    public static function calendarDaysBetween(Carbon|string $depuis, Carbon|string $jour): int
    {
        $a = Carbon::parse($depuis)->startOfDay();
        $b = Carbon::parse($jour)->startOfDay();

        return (int) $a->diffInDays($b, false);
    }

    /**
     * Indice de service (0 ou 1) pour une date, depuis le début de cycle.
     */
    public static function indiceEnService(Carbon|string $depuis, Carbon|string $jour): int
    {
        $dayIndex = max(0, self::calendarDaysBetween($depuis, $jour));

        return (int) floor($dayIndex / self::BLOC_JOURS) % self::MAX_PAR_ZONE;
    }

    public static function estEnService(
        ?int $indiceReleve,
        Carbon|string|null $releveDepuis,
        Carbon|string|null $jour = null,
    ): bool {
        // Seul sur la zone / pas encore en binôme → toujours en service.
        if ($indiceReleve === null || $releveDepuis === null) {
            return true;
        }

        $jour ??= now();

        return self::indiceEnService($releveDepuis, $jour) === (int) $indiceReleve;
    }
}
