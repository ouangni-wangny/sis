<?php

namespace App\Application\Reporting;

use App\Domain\Shared\Enums\ResultatControle;
use App\Domain\Shared\Enums\StatutVacation;
use App\Domain\Shared\Support\ShiftSchedule;
use App\Models\Controle;
use App\Models\Vacation;
use Illuminate\Support\Collection;

/**
 * Pointage des créneaux à partir des contrôles de présence.
 *
 * - contrôle « présent » sur le créneau → présent, heures comptées
 * - contrôle « absent »                 → absent, 0 h
 * - aucun contrôle                      → non contrôlé : ni présent ni absent,
 *   le créneau reste tel quel et ses heures ne sont PAS comptabilisées.
 *
 * - vacation « à recouvrir »            → absent (absence enregistrée), 0 h
 *
 * Un contrôle est rattaché à un seul créneau (celui qui le contient, sinon le
 * plus proche dans la tolérance) ; sur un même créneau, le plus récent l’emporte.
 */
final class PointageVacationsAction
{
    public const PRESENT = 'present';

    public const ABSENT = 'absent';

    public const NON_CONTROLE = 'non_controle';

    /**
     * @param  Collection<int, Vacation>  $vacations
     * @return list<array{vacation: Vacation, date: string, controle: string, heures_prevues: float, heures_comptees: float}>
     */
    public function execute(Collection $vacations, ?string $from = null, ?string $to = null): array
    {
        $vacations = $vacations
            ->reject(fn (Vacation $v) => $v->statut === StatutVacation::Annulee)
            ->values();

        if ($vacations->isEmpty()) {
            return [];
        }

        $fromDay = $from ? ShiftSchedule::dayNumber($from) : null;
        // Une affectation sans fin s’arrête à la borne demandée, sinon à aujourd’hui.
        $toDay = ShiftSchedule::dayNumber($to ?? now()->toDateString());

        $tolerance = (int) config('sis.controle.heure_tolerance_minutes', 60);
        $controlesParAgent = $this->controlesParAgent($vacations, $fromDay, $toDay);

        // 1. Créneaux de la période.
        $creneaux = [];
        foreach ($vacations as $vacation) {
            $schedule = ShiftSchedule::fromVacation($vacation);
            $first = max($schedule->firstDay, $fromDay ?? $schedule->firstDay);
            $last = min($schedule->lastDay, $toDay);

            for ($day = $first; $day <= $last; $day++) {
                [$start, $end] = $schedule->windowOnDay($day);
                $creneaux[] = [
                    'vacation' => $vacation,
                    'day' => $day,
                    'start' => $start,
                    'end' => $end,
                    'heures' => round($schedule->spanMinutes / 60, 2),
                    'controle' => null,
                ];
            }
        }

        // 2. Chaque contrôle est rattaché à un seul créneau de l’agent : celui
        //    qui le contient, sinon le plus proche dans la tolérance. Évite
        //    qu’un contrôle à 19h30 valide à la fois le jour (7h–19h) et la nuit.
        $parAgent = [];
        foreach ($creneaux as $i => $c) {
            $parAgent[(string) $c['vacation']->agent_id][] = $i;
        }
        foreach ($controlesParAgent as $agentId => $controles) {
            $indices = $parAgent[$agentId] ?? [];
            foreach ($controles->sortBy('minute') as $controle) {
                $i = $this->creneauDuControle($creneaux, $indices, $controle['minute'], $tolerance);
                if ($i !== null) {
                    // Contrôles triés : le plus récent l’emporte.
                    $creneaux[$i]['controle'] = $controle['resultat'];
                }
            }
        }

        // 3. Lignes : « à recouvrir » = absence enregistrée (0 h).
        $lignes = [];
        foreach ($creneaux as $c) {
            $resultat = $c['vacation']->statut === StatutVacation::ARecouvrir
                ? self::ABSENT
                : ($c['controle'] ?? self::NON_CONTROLE);

            $lignes[] = [
                'vacation' => $c['vacation'],
                'date' => ShiftSchedule::isoFromDayNumber($c['day']),
                'controle' => $resultat,
                'heures_prevues' => $c['heures'],
                'heures_comptees' => $resultat === self::PRESENT ? $c['heures'] : 0.0,
            ];
        }

        return $lignes;
    }

    /**
     * @param  list<array{start: int, end: int}>  $creneaux
     * @param  list<int>  $indices
     */
    private function creneauDuControle(array $creneaux, array $indices, int $minute, int $tolerance): ?int
    {
        $best = null;
        $bestDistance = null;
        foreach ($indices as $i) {
            $c = $creneaux[$i];
            $distance = $minute < $c['start']
                ? $c['start'] - $minute
                : max(0, $minute - $c['end']);
            // Bornes [début, fin[ : à 19:00 pile, le créneau qui commence l’emporte.
            if ($distance === 0 && $minute === $c['end']) {
                $distance = 1;
            }
            if ($distance <= $tolerance && ($bestDistance === null || $distance < $bestDistance)) {
                $best = $i;
                $bestDistance = $distance;
            }
        }

        return $best;
    }

    /**
     * @param  list<array{controle: string, heures_comptees: float}>  $lignes
     * @return array{present: int, absent: int, non_controle: int, heures_comptees: float}
     */
    public static function totaux(array $lignes): array
    {
        $totaux = [self::PRESENT => 0, self::ABSENT => 0, self::NON_CONTROLE => 0, 'heures_comptees' => 0.0];
        foreach ($lignes as $ligne) {
            $totaux[$ligne['controle']]++;
            $totaux['heures_comptees'] += $ligne['heures_comptees'];
        }
        $totaux['heures_comptees'] = round($totaux['heures_comptees'], 2);

        return $totaux;
    }

    /**
     * @param  Collection<int, Vacation>  $vacations
     * @return Collection<string, Collection<int, array{minute: int, resultat: string}>>
     */
    private function controlesParAgent(Collection $vacations, ?int $fromDay, int $toDay): Collection
    {
        $minDay = $vacations->min(fn (Vacation $v) => ShiftSchedule::fromVacation($v)->firstDay);
        $minDay = max($minDay, $fromDay ?? $minDay);

        return Controle::query()
            ->whereIn('controle_agent_id', $vacations->pluck('agent_id')->unique()->values())
            ->where('effectue_at', '>=', ShiftSchedule::isoFromDayNumber($minDay - 1))
            ->where('effectue_at', '<', ShiftSchedule::isoFromDayNumber($toDay + 3))
            ->get(['controle_agent_id', 'effectue_at', 'resultat'])
            ->map(fn (Controle $c) => [
                'agent_id' => (string) $c->controle_agent_id,
                'minute' => intdiv($c->effectue_at->getTimestamp(), 60),
                // Contrôle historique sans résultat = présence constatée.
                'resultat' => $c->resultat === ResultatControle::Absent ? self::ABSENT : self::PRESENT,
            ])
            ->groupBy('agent_id');
    }
}
