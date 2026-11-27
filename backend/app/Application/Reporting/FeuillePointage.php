<?php

namespace App\Application\Reporting;

use App\Domain\Shared\Enums\TypeAgent;
use App\Domain\Shared\Support\ShiftSchedule;
use App\Models\Agent;
use App\Models\Vacation;
use Carbon\CarbonImmutable;

/**
 * Feuille de pointage mensuelle (état de présence) : une ligne par agent,
 * une colonne par jour, regroupée par catégorie (agents postés, rondiers,
 * contrôleurs).
 *
 * Case du jour, à partir des lignes de {@see PointageVacationsAction} :
 * - nombre de créneaux présents (« 1 », « 2 » pour une double vacation) ;
 * - sinon « A » si un créneau est absent ;
 * - sinon « NC » si le créneau n’a pas été contrôlé ;
 * - sinon vide (pas de créneau ce jour-là).
 *
 * Total = jours de travail effectués = somme des créneaux présents.
 */
final class FeuillePointage
{
    public const ABSENT = 'A';

    public const NON_CONTROLE = 'NC';

    /** Catégorie des agents de relève (grade « rondier », type agent). */
    public const RONDIER = 'rondier';

    /** Ordre et intitulé des catégories, comme sur la feuille papier. */
    public const CATEGORIES = [
        TypeAgent::Agent->value => 'Agents postés',
        self::RONDIER => 'Rondiers',
        TypeAgent::Controleur->value => 'Contrôleurs',
        TypeAgent::Administration->value => 'Administration',
    ];

    /**
     * @param  list<array{vacation: Vacation, date: string, controle: string}>  $lignes
     * @param  iterable<Agent>  $agents  agents à faire figurer même sans créneau
     * @return array{jours: list<array{date: string, jour: string}>, categories: list<array{type: string, libelle: string, agents: list<array<string, mixed>>}>}
     */
    public static function construire(array $lignes, iterable $agents, string $from, string $to): array
    {
        $jours = self::jours($from, $to);

        /** @var array<string, array<string, array{present: int, absent: int, non_controle: int}>> $cases */
        $cases = [];
        $parId = [];
        foreach ($agents as $agent) {
            $parId[(string) $agent->id] = $agent;
            $cases[(string) $agent->id] ??= [];
        }
        foreach ($lignes as $ligne) {
            $agentId = (string) $ligne['vacation']->agent_id;
            $parId[$agentId] ??= $ligne['vacation']->agent;
            $cases[$agentId][$ligne['date']] ??= [
                PointageVacationsAction::PRESENT => 0,
                PointageVacationsAction::ABSENT => 0,
                PointageVacationsAction::NON_CONTROLE => 0,
            ];
            $cases[$agentId][$ligne['date']][$ligne['controle']]++;
        }

        $groupes = [];
        foreach ($cases as $agentId => $parJour) {
            $agent = $parId[$agentId] ?? null;
            if (! $agent) {
                continue;
            }

            $valeurs = [];
            $total = 0;
            $absences = 0;
            foreach ($jours as $jour) {
                $c = $parJour[$jour['date']] ?? null;
                $valeurs[$jour['date']] = self::valeur($c);
                $total += $c[PointageVacationsAction::PRESENT] ?? 0;
                $absences += $valeurs[$jour['date']] === self::ABSENT ? 1 : 0;
            }

            $type = $agent->type instanceof TypeAgent ? $agent->type->value : (string) $agent->type;
            if ($type === TypeAgent::Agent->value && $agent->relationLoaded('grade') && $agent->grade?->est_rondier) {
                $type = self::RONDIER;
            }
            $contrat = $agent->relationLoaded('contratActif') ? $agent->contratActif?->type : null;

            $groupes[$type][] = [
                'agent_id' => $agentId,
                'nom' => mb_strtoupper(trim($agent->nom.' '.$agent->prenom)),
                'matricule' => $agent->matricule,
                'contrat' => $contrat instanceof \BackedEnum ? $contrat->value : $contrat,
                'jours' => $valeurs,
                'total' => $total,
                'absences' => $absences,
            ];
        }

        $categories = [];
        foreach ($groupes as $type => $rows) {
            usort($rows, fn (array $a, array $b) => strcasecmp($a['nom'], $b['nom']));
            $categories[] = [
                'type' => $type,
                'libelle' => self::CATEGORIES[$type] ?? ucfirst($type),
                'agents' => $rows,
            ];
        }
        $ordre = array_flip(array_keys(self::CATEGORIES));
        usort($categories, fn (array $a, array $b) => ($ordre[$a['type']] ?? 99) <=> ($ordre[$b['type']] ?? 99));

        return ['jours' => $jours, 'categories' => $categories];
    }

    /** @return list<array{date: string, jour: string}> */
    private static function jours(string $from, string $to): array
    {
        $lettres = ['D', 'L', 'M', 'M', 'J', 'V', 'S'];
        $jours = [];
        for ($d = ShiftSchedule::dayNumber($from); $d <= ShiftSchedule::dayNumber($to); $d++) {
            $date = ShiftSchedule::isoFromDayNumber($d);
            $jours[] = [
                'date' => $date,
                'jour' => $lettres[(int) CarbonImmutable::parse($date)->dayOfWeek],
            ];
        }

        return $jours;
    }

    /** @param  array{present: int, absent: int, non_controle: int}|null  $c */
    private static function valeur(?array $c): ?string
    {
        if ($c === null) {
            return null;
        }
        if ($c[PointageVacationsAction::PRESENT] > 0) {
            return (string) $c[PointageVacationsAction::PRESENT];
        }
        if ($c[PointageVacationsAction::ABSENT] > 0) {
            return self::ABSENT;
        }

        return self::NON_CONTROLE;
    }
}
