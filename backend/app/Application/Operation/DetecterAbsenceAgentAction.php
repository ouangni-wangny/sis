<?php

namespace App\Application\Operation;

use App\Domain\Shared\Enums\StatutAbsence;
use App\Domain\Shared\Exceptions\DomainException;
use App\Domain\Shared\Support\ShiftSchedule;
use App\Models\Absence;
use App\Models\Agent;

final class DetecterAbsenceAgentAction
{
    public function assertDisponible(
        string $agentId,
        string $dateDebut,
        string $heureDebut,
        string $heureFin,
        ?string $dateFin = null,
    ): void {
        // Jours de prise de poste : la nuit qui finit le matin du premier jour d’absence reste due.
        $schedule = ShiftSchedule::fromStored($dateDebut, $heureDebut, $heureFin, $dateFin);

        $absences = Absence::query()
            ->where('agent_id', $agentId)
            ->whereIn('statut', [StatutAbsence::Approuvee->value, 'approuvee'])
            ->get()
            ->filter(fn (Absence $a) => $schedule->firstDay <= ShiftSchedule::dayNumber(($a->date_fin ?? $a->date_debut)->toDateString())
                && $schedule->lastDay >= ShiftSchedule::dayNumber($a->date_debut->toDateString()));

        if ($absences->isNotEmpty()) {
            $absence = $absences->first();

            throw new DomainException(sprintf(
                '%s : absence approuvée du %s au %s.',
                Agent::query()->find($agentId)?->libelle() ?? 'Cet agent',
                $absence->date_debut->format('d/m/Y'),
                ($absence->date_fin ?? $absence->date_debut)->format('d/m/Y'),
            ));
        }
    }
}
