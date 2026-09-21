<?php

namespace App\Application\Operation;

use App\Domain\Shared\Support\ShiftInterval;
use App\Models\Vacation;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final class DetecterConflitsVacationAction
{
    /**
     * Horizon utilisé pour représenter une vacation "sans fin définie"
     * (date_fin null = affectation récurrente/continue, pas une vacation
     * d'un seul jour) dans les comparaisons de créneaux.
     */
    private const OPEN_ENDED_HORIZON_YEARS = 5;

    public function execute(
        string $agentId,
        string $dateDebut,
        string $heureDebut,
        string $heureFin,
        ?string $dateFin = null,
        ?string $excludeId = null,
    ): Collection {
        [$start, $end] = $this->range($dateDebut, $heureDebut, $heureFin, $dateFin);

        return Vacation::query()
            ->where('agent_id', $agentId)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->whereNotIn('statut', ['annulee', 'a_recouvrir'])
            ->get()
            ->filter(function (Vacation $v) use ($start, $end) {
                [$vStart, $vEnd] = $this->range(
                    $v->date_debut->format('Y-m-d'),
                    $v->heure_debut,
                    $v->heure_fin,
                    $v->date_fin?->format('Y-m-d'),
                );

                return $start->lt($vEnd) && $end->gt($vStart);
            })
            ->values();
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function range(
        string $dateDebut,
        string $heureDebut,
        string $heureFin,
        ?string $dateFin,
    ): array {
        $heureDebut = ShiftInterval::hm($heureDebut);
        $heureFin = ShiftInterval::hm($heureFin);
        $start = Carbon::parse("{$dateDebut} {$heureDebut}");

        if ($dateFin === null) {
            // Sans fin définie : horizon lointain. Overnight / cycle : +1 j
            // sur l’heure de fin pour ne pas coller à 0h de span.
            $end = $start->copy()->addYears(self::OPEN_ENDED_HORIZON_YEARS)
                ->setTimeFromTimeString($heureFin);
            if (ShiftInterval::needsNextCalendarDay($heureDebut, $heureFin)) {
                $end = $end->copy()->addDay();
            }

            return [$start, $end];
        }

        $endDate = ShiftInterval::dateFinForShift(
            $dateDebut,
            $heureDebut,
            $heureFin,
            $dateFin,
        ) ?? $dateFin;

        return [$start, Carbon::parse("{$endDate} {$heureFin}")];
    }
}
