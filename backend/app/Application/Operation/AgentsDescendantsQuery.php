<?php

namespace App\Application\Operation;

use App\Domain\Shared\Enums\StatutVacation;
use App\Domain\Shared\Support\ShiftSchedule;
use App\Models\Vacation;
use Illuminate\Support\Collection;

/** Agents qui finissent leur vacation sur le site quand le créneau à recouvrir commence. */
final class AgentsDescendantsQuery
{
    /** @return Collection<int, Vacation> Vacations de ces agents, même poste d’abord. */
    public static function pour(Vacation $trou): Collection
    {
        $schedule = ShiftSchedule::fromVacation($trou);
        [$debutTrou] = $schedule->windowOnDay($schedule->firstDay);
        $tolerance = (int) config('sis.controle.heure_tolerance_minutes', 60);
        $veille = ShiftSchedule::isoFromDayNumber($schedule->firstDay - 1);

        return Vacation::query()
            ->with(['agent', 'poste:id,nom'])
            ->where('site_id', $trou->site_id)
            ->where('agent_id', '!=', $trou->agent_id)
            ->whereNotIn('statut', [StatutVacation::Annulee->value, StatutVacation::ARecouvrir->value])
            ->whereDate('date_debut', '<=', $trou->date_debut)
            ->where(fn ($q) => $q->whereNull('date_fin')->orWhereDate('date_fin', '>=', $veille))
            ->get()
            ->filter(function (Vacation $v) use ($schedule, $debutTrou, $tolerance) {
                $autre = ShiftSchedule::fromVacation($v);
                foreach ([$schedule->firstDay - 1, $schedule->firstDay] as $jour) {
                    if ($jour < $autre->firstDay || $jour > $autre->lastDay) {
                        continue;
                    }
                    [, $fin] = $autre->windowOnDay($jour);
                    if (abs($fin - $debutTrou) <= $tolerance) {
                        return true;
                    }
                }

                return false;
            })
            ->sortBy(fn (Vacation $v) => (string) $v->poste_id === (string) $trou->poste_id ? 0 : 1)
            ->unique('agent_id')
            ->values();
    }
}
