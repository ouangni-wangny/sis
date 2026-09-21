<?php

namespace App\Application\Operation;

use App\Domain\Shared\Enums\StatutAbsence;
use App\Domain\Shared\Exceptions\DomainException;
use App\Models\Absence;
use Carbon\Carbon;

final class DetecterAbsenceAgentAction
{
    public function assertDisponible(
        string $agentId,
        string $dateDebut,
        string $heureDebut,
        string $heureFin,
        ?string $dateFin = null,
    ): void {
        $start = Carbon::parse("{$dateDebut} {$heureDebut}");
        $endDate = $dateFin ?? $dateDebut;
        $end = Carbon::parse("{$endDate} {$heureFin}");
        if ($heureFin < $heureDebut) {
            $end->addDay();
        }

        $absences = Absence::query()
            ->where('agent_id', $agentId)
            ->whereIn('statut', [StatutAbsence::Approuvee->value, 'approuvee'])
            ->get()
            ->filter(function (Absence $a) use ($start, $end) {
                $aStart = $a->date_debut->copy()->startOfDay();
                $aEnd = ($a->date_fin ?? $a->date_debut)->copy()->endOfDay();

                return $start->lte($aEnd) && $end->gte($aStart);
            });

        if ($absences->isNotEmpty()) {
            throw new DomainException(
                'Agent indisponible : absence approuvée sur cette période.'
            );
        }
    }
}
