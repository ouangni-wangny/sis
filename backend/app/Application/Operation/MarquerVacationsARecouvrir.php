<?php

namespace App\Application\Operation;

use App\Domain\Shared\Enums\StatutVacation;
use App\Domain\Shared\Support\ShiftInterval;
use App\Domain\Shared\Support\ShiftSchedule;
use App\Models\Absence;
use App\Models\Vacation;

/**
 * Passe en « à recouvrir » les vacations d’un agent qui chevauchent
 * une période d’absence (logique partagée RH / contrôle terrain).
 */
final class MarquerVacationsARecouvrir
{
    public static function execute(
        Absence $absence,
        string $agentId,
        string $debut,
        string $fin,
    ): int {
        $vacations = Vacation::query()
            ->where('agent_id', $agentId)
            ->whereIn('statut', [
                StatutVacation::Planifiee->value,
                StatutVacation::EnCours->value,
            ])
            ->whereDate('date_debut', '<=', $fin)
            ->where(function ($q) use ($debut) {
                $q->whereNull('date_fin')
                    ->orWhereDate('date_fin', '>=', $debut);
            })
            ->get()
            ->filter(fn (Vacation $v) => self::vacationPlanningDayOverlapsAbsence($v, $debut, $fin));

        foreach ($vacations as $vacation) {
            self::isolerJoursAbsents($vacation, $debut, $fin)->update([
                'statut' => StatutVacation::ARecouvrir->value,
                'absence_id' => $absence->id,
            ]);
        }

        return $vacations->count();
    }

    /**
     * Seuls les jours d’absence passent « à recouvrir » : une affectation sur
     * période (ou sans fin) est découpée en avant / absence / après, pour que
     * le titulaire garde ses autres jours et que le remplaçant ne reprenne
     * que les jours d’absence.
     *
     * @return Vacation la vacation couvrant les jours d’absence
     */
    private static function isolerJoursAbsents(Vacation $v, string $debut, string $fin): Vacation
    {
        $schedule = ShiftSchedule::fromVacation($v);
        $trouDebut = max($schedule->firstDay, ShiftSchedule::dayNumber($debut));
        $trouFin = min($schedule->lastDay, ShiftSchedule::dayNumber($fin));

        if ($trouDebut === $schedule->firstDay && $trouFin === $schedule->lastDay) {
            return $v;
        }

        $nextDay = ShiftInterval::needsNextCalendarDay((string) $v->heure_debut, (string) $v->heure_fin);
        // date_fin stockée = fin calendaire du dernier créneau (nuit → lendemain).
        $finStockee = fn (int $jour) => ShiftSchedule::isoFromDayNumber($nextDay ? $jour + 1 : $jour);

        $apres = null;
        if ($trouFin < $schedule->lastDay) {
            $apres = $v->replicate();
            $apres->date_debut = ShiftSchedule::isoFromDayNumber($trouFin + 1);
            $apres->date_fin = $v->date_fin === null ? null : $v->date_fin->toDateString();
            $apres->save();
        }

        if ($trouDebut > $schedule->firstDay) {
            $trou = $v->replicate();
            $trou->date_debut = ShiftSchedule::isoFromDayNumber($trouDebut);
            $trou->date_fin = $finStockee($trouFin);
            $trou->save();

            $v->update(['date_fin' => $finStockee($trouDebut - 1)]);
            RattacherRondesApresDecoupage::execute($v, array_values(array_filter([$v, $trou, $apres])), $v);

            return $trou;
        }

        $v->update(['date_fin' => $finStockee($trouFin)]);
        // Ici $apres existe (sinon toute la vacation serait absente) : il reste planifié.
        RattacherRondesApresDecoupage::execute($v, array_values(array_filter([$v, $apres])), $apres ?? $v);

        return $v;
    }

    /** Un jour de prise de poste de la vacation tombe dans l’absence. */
    public static function vacationPlanningDayOverlapsAbsence(
        Vacation $v,
        string $debut,
        string $fin,
    ): bool {
        $schedule = ShiftSchedule::fromVacation($v);

        return $schedule->firstDay <= ShiftSchedule::dayNumber($fin)
            && $schedule->lastDay >= ShiftSchedule::dayNumber($debut);
    }
}
