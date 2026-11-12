<?php

namespace App\Application\Operation;

use App\Domain\Shared\Enums\StatutAbsence;
use App\Domain\Shared\Enums\StatutAgent;
use App\Domain\Shared\Enums\StatutVacation;
use App\Domain\Shared\Enums\TypeAgent;
use App\Domain\Shared\Support\ShiftSchedule;
use App\Models\Absence;
use App\Models\Agent;
use App\Models\Vacation;
use Illuminate\Support\Collection;

/**
 * Agents qui peuvent prendre une case à recouvrir : libres sur le créneau, et
 * d’abord ceux qui descendent à cet instant (l’agent qui enchaîne).
 */
final class RemplacantsDisponiblesQuery
{
    /** @return Collection<int, array{agent_id: string, agent: string, matricule: ?string, descend: bool, meme_poste: bool, pool_siege: bool}> */
    public static function pour(Vacation $trou): Collection
    {
        $schedule = ShiftSchedule::fromVacation($trou);
        $premier = ShiftSchedule::isoFromDayNumber($schedule->firstDay);
        $dernier = ShiftSchedule::isoFromDayNumber($schedule->lastDay);
        $descendants = AgentsDescendantsQuery::pour($trou)->keyBy('agent_id');

        $agents = Agent::query()
            ->where('type', TypeAgent::Agent)
            ->whereIn('statut', [StatutAgent::Disponible, StatutAgent::EnActivite])
            ->whereKeyNot($trou->agent_id)
            ->avecContratValide($premier)
            ->get();

        $absents = Absence::query()
            ->whereIn('agent_id', $agents->modelKeys())
            ->where('statut', StatutAbsence::Approuvee->value)
            ->whereDate('date_debut', '<=', $dernier)
            ->where(fn ($q) => $q->whereNull('date_fin')->orWhereDate('date_fin', '>=', $premier))
            ->pluck('agent_id');

        // La présence du pool siège se libère d’elle-même au recouvrement : elle n’occupe pas l’agent.
        $sieges = $agents->pluck('poste_siege_id', 'id');
        $occupes = Vacation::query()
            ->whereIn('agent_id', $agents->modelKeys())
            ->whereNotIn('statut', [StatutVacation::Annulee->value, StatutVacation::ARecouvrir->value])
            ->whereDate('date_debut', '<=', ShiftSchedule::isoFromDayNumber($schedule->lastDay + 1))
            ->where(fn ($q) => $q->whereNull('date_fin')->orWhereDate('date_fin', '>=', ShiftSchedule::isoFromDayNumber($schedule->firstDay - 1)))
            ->get()
            ->filter(fn (Vacation $v) => (string) $v->poste_id !== (string) ($sieges[$v->agent_id] ?? '')
                && $schedule->overlaps(ShiftSchedule::fromVacation($v)))
            ->pluck('agent_id');

        return $agents
            ->reject(fn (Agent $a) => $absents->contains($a->id) || $occupes->contains($a->id))
            ->map(fn (Agent $a) => [
                'agent_id' => (string) $a->id,
                'agent' => trim($a->prenom.' '.$a->nom),
                'matricule' => $a->matricule,
                'descend' => $descendants->has($a->id),
                'meme_poste' => (string) $descendants->get($a->id)?->poste_id === (string) $trou->poste_id,
                'pool_siege' => (bool) $a->pool_siege,
            ])
            ->sortBy(fn (array $r) => [$r['descend'] ? 0 : 1, $r['meme_poste'] ? 0 : 1, $r['pool_siege'] ? 0 : 1, $r['agent']])
            ->values();
    }
}
