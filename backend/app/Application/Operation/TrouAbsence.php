<?php

namespace App\Application\Operation;

use App\Domain\Shared\Enums\StatutAbsence;
use App\Domain\Shared\Enums\StatutVacation;
use App\Domain\Shared\Support\ShiftInterval;
use App\Domain\Shared\Support\ShiftSchedule;
use App\Models\Absence;
use App\Models\Vacation;

/** Case du planning d’un agent en absence approuvée : à recouvrir, pas à remplir. */
final class TrouAbsence
{
    public static function absenceDuJour(string $agentId, string $date): ?Absence
    {
        return Absence::query()
            ->where('agent_id', $agentId)
            ->where('statut', StatutAbsence::Approuvee->value)
            ->whereDate('date_debut', '<=', $date)
            ->where(fn ($q) => $q->whereNull('date_fin')->orWhereDate('date_fin', '>=', $date))
            ->first();
    }

    /** @param  array<string, mixed>  $base  agent, site, poste et horaires du créneau */
    public static function creer(array $base, string $date, Absence $absence): Vacation
    {
        return Vacation::query()->create([
            ...$base,
            'date_debut' => $date,
            'date_fin' => ShiftInterval::needsNextCalendarDay((string) $base['heure_debut'], (string) $base['heure_fin'])
                ? ShiftSchedule::isoFromDayNumber(ShiftSchedule::dayNumber($date) + 1)
                : $date,
            'statut' => StatutVacation::ARecouvrir->value,
            'absence_id' => $absence->id,
        ]);
    }
}
