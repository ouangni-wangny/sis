<?php

namespace App\Application\Operation;

use App\Models\Agent;
use App\Models\Vacation;
use App\Domain\Shared\Support\ShiftInterval;
use App\Domain\Shared\Support\ShiftSchedule;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Contraintes horaires d'une vacation : durée maximale d'un créneau, et
 * repos minimum entre deux vacations consécutives du même agent.
 * Seuils configurables via config('sis.vacation'), cf. config/sis.php.
 */
final class AssertVacationDureeLegale
{
    /**
     * @param  array{allow_double_quart?: bool, ignore_repos?: bool, poste_id?: string|null, exclude_ids?: list<string>}  $options
     */
    public static function execute(
        string $agentId,
        string $dateDebut,
        string $heureDebut,
        string $heureFin,
        ?string $dateFin = null,
        ?string $excludeId = null,
        array $options = [],
    ): void {
        $agentLabel = self::agentLabel($agentId);

        $dureeMax = (float) config('sis.vacation.duree_max_heures');
        $dureeHeures = ShiftInterval::durationHours($heureDebut, $heureFin);
        $isCycle24h = ShiftInterval::isCycle24h($heureDebut, $heureFin);

        // Cycle 24h (relève→relève) : exemption explicite du plafond créneau
        // (un agent seul sur surveillance continue). Les moitiés restent ≤ max.
        if (! $isCycle24h && $dureeHeures > $dureeMax) {
            throw ValidationException::withMessages([
                'heure_fin' => sprintf(
                    '%s — durée de vacation trop longue : %.1fh (maximum %.1fh).',
                    $agentLabel,
                    $dureeHeures,
                    $dureeMax,
                ),
            ]);
        }

        $blockers = self::findReposInsuffisant(
            $agentId,
            $dateDebut,
            $heureDebut,
            $heureFin,
            $dateFin,
            $excludeId,
            $options,
        );

        if ($blockers->isEmpty()) {
            return;
        }

        $other = $blockers->first();
        $reposMin = (float) config('sis.vacation.repos_min_heures');
        $closest = ShiftSchedule::fromStored($dateDebut, $heureDebut, $heureFin, $dateFin)
            ->closestGap(ShiftSchedule::fromVacation($other));
        $gapMinutes = $closest['gap'] ?? 0;
        $relation = $closest !== null && $closest['mine'][1] <= $closest['other'][0]
            ? 'avant'
            : 'après';

        $lieu = self::vacationLieu($other);
        throw ValidationException::withMessages([
            'heure_debut' => sprintf(
                '%s : impossible de planifier le %s (%s–%s) — seulement %.1fh de repos %s sa vacation déjà planifiée le %s (%s–%s)%s (minimum %.1fh).',
                $agentLabel,
                self::formatDateFr($dateDebut),
                substr($heureDebut, 0, 5),
                substr($heureFin, 0, 5),
                $gapMinutes / 60,
                $relation,
                self::formatDateFr($other->date_debut->format('Y-m-d')),
                substr((string) $other->heure_debut, 0, 5),
                substr((string) $other->heure_fin, 0, 5),
                $lieu !== '' ? " ({$lieu})" : '',
                $reposMin,
            ),
            'blocking_vacation_id' => [(string) $other->id],
            'blocking_vacation_ids' => $blockers->pluck('id')->map(fn ($id) => (string) $id)->all(),
        ]);
    }

    /**
     * Vacations du même agent trop proches (repos < minimum légal).
     *
     * @param  array{allow_double_quart?: bool, ignore_repos?: bool, poste_id?: string|null, exclude_ids?: list<string>}  $options
     * @return Collection<int, Vacation>
     */
    public static function findReposInsuffisant(
        string $agentId,
        string $dateDebut,
        string $heureDebut,
        string $heureFin,
        ?string $dateFin = null,
        ?string $excludeId = null,
        array $options = [],
    ): Collection {
        if ($options['ignore_repos'] ?? false) {
            return collect();
        }

        $proposed = ShiftSchedule::fromStored($dateDebut, $heureDebut, $heureFin, $dateFin);

        $reposMin = (float) config('sis.vacation.repos_min_heures');
        $posteId = $options['poste_id'] ?? null;
        /** @var list<string> $excludeIds */
        $excludeIds = array_values(array_filter([
            ...($options['exclude_ids'] ?? []),
            $excludeId,
        ]));

        $others = Vacation::query()
            ->with(['site:id,nom', 'poste:id,nom'])
            ->where('agent_id', $agentId)
            ->whereNotNull('date_fin')
            ->whereNotIn('statut', ['annulee', 'a_recouvrir'])
            ->when($excludeIds !== [], fn ($q) => $q->whereNotIn('id', $excludeIds))
            ->get();

        return $others
            ->filter(function (Vacation $other) use ($proposed, $reposMin, $posteId, $heureDebut, $heureFin) {
                // Chevauchement (null) : géré par DetecterConflitsVacationAction.
                $closest = $proposed->closestGap(ShiftSchedule::fromVacation($other));
                if ($closest === null) {
                    return false;
                }

                $gapMinutes = $closest['gap'];
                [$start, $end] = $closest['mine'];
                [$oStart, $oEnd] = $closest['other'];

                // Relève continue autorisée (même poste, gap ≤ 1 min) uniquement si :
                // - cycle 24h → cycle 24h (relève→relève), ou
                // - quart jour → quart nuit (enchaînement diurne puis overnight).
                // Interdit : nuit → jour (0h de repos après une garde de nuit).
                $samePoste = $posteId
                    && $other->poste_id
                    && (string) $other->poste_id === (string) $posteId;
                if ($samePoste && $gapMinutes <= 1) {
                    $proposedCycle = ShiftInterval::isCycle24h($heureDebut, $heureFin);
                    $otherCycle = ShiftInterval::isCycle24h(
                        (string) $other->heure_debut,
                        (string) $other->heure_fin,
                    );
                    if ($proposedCycle && $otherCycle) {
                        return false;
                    }

                    $proposedNight = ShiftInterval::isOvernight($heureDebut, $heureFin);
                    $otherNight = ShiftInterval::isOvernight(
                        (string) $other->heure_debut,
                        (string) $other->heure_fin,
                    );

                    // other puis proposed : jour → nuit
                    if ($oEnd === $start && ! $otherNight && ! $otherCycle && $proposedNight) {
                        return false;
                    }
                    // proposed puis other : jour → nuit
                    if ($end === $oStart && ! $proposedNight && ! $proposedCycle && $otherNight) {
                        return false;
                    }
                }

                return $gapMinutes / 60 < $reposMin;
            })
            ->values();
    }

    private static function formatDateFr(string $isoDate): string
    {
        return Carbon::parse($isoDate)->format('d/m/Y');
    }

    private static function vacationLieu(Vacation $vacation): string
    {
        $parts = array_values(array_filter([
            $vacation->site?->nom,
            $vacation->poste?->nom,
        ]));

        return implode(' / ', $parts);
    }

    private static function agentLabel(string $agentId): string
    {
        $agent = Agent::query()->find($agentId);
        if (! $agent) {
            return 'Agent';
        }

        return trim(sprintf(
            '%s %s (%s)',
            $agent->prenom,
            $agent->nom,
            $agent->matricule,
        ));
    }
}
