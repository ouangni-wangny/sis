<?php

namespace App\Application\Operation;

use App\Domain\Shared\Support\ShiftSchedule;
use App\Models\Ronde;
use App\Models\Vacation;

/**
 * Après découpage d’une vacation en plusieurs morceaux (absence, extension
 * sur période), chaque ronde liée suit le bon morceau :
 * - ronde démarrée : morceau dont le créneau contient son démarrage ;
 * - ronde non démarrée (pas de date) : morceau par défaut (resté planifié).
 */
final class RattacherRondesApresDecoupage
{
    /** @param  list<Vacation>  $morceaux  morceaux issus de $origine (lui compris) */
    public static function execute(Vacation $origine, array $morceaux, Vacation $parDefaut): void
    {
        $rondes = Ronde::query()->where('vacation_id', $origine->id)->get();
        if ($rondes->isEmpty()) {
            return;
        }

        $schedules = array_map(fn (Vacation $v) => [$v, ShiftSchedule::fromVacation($v)], $morceaux);

        foreach ($rondes as $ronde) {
            $cible = $ronde->demarree_at
                ? self::morceauContenant($schedules, intdiv($ronde->demarree_at->getTimestamp(), 60))
                : null;
            $cible ??= $parDefaut;

            if ($ronde->vacation_id !== $cible->id) {
                $ronde->update(['vacation_id' => $cible->id]);
            }
        }
    }

    /** @param  list<array{0: Vacation, 1: ShiftSchedule}>  $schedules */
    private static function morceauContenant(array $schedules, int $minute): ?Vacation
    {
        $jour = intdiv($minute, 1440);
        foreach ($schedules as [$vacation, $schedule]) {
            // Prise de poste le jour même ou la veille (nuit / 24h).
            foreach ([$jour, $jour - 1] as $d) {
                if (! $schedule->coversDay($d)) {
                    continue;
                }
                [$start, $end] = $schedule->windowOnDay($d);
                if ($minute >= $start && $minute < $end) {
                    return $vacation;
                }
            }
        }

        return null;
    }
}
