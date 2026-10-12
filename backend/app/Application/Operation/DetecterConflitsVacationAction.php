<?php

namespace App\Application\Operation;

use App\Domain\Shared\Support\ShiftSchedule;
use App\Models\Vacation;
use Illuminate\Support\Collection;

final class DetecterConflitsVacationAction
{
    /**
     * Vacations de l’agent dont un créneau chevauche réellement un créneau proposé.
     *
     * Comparaison créneau par créneau (un par jour de prise de poste) : une
     * vacation sur période n’occupe pas l’agent 24h/24, il reste libre pour un
     * autre site en dehors de ses horaires (remplacement, quart supplémentaire).
     */
    public function execute(
        string $agentId,
        string $dateDebut,
        string $heureDebut,
        string $heureFin,
        ?string $dateFin = null,
        ?string $excludeId = null,
    ): Collection {
        $proposed = ShiftSchedule::fromStored($dateDebut, $heureDebut, $heureFin, $dateFin);

        return Vacation::query()
            ->where('agent_id', $agentId)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->whereNotIn('statut', ['annulee', 'a_recouvrir'])
            ->get()
            ->filter(fn (Vacation $v) => $proposed->overlaps(ShiftSchedule::fromVacation($v)))
            ->values();
    }
}
