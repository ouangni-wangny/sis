<?php

namespace App\Application\Reporting;

use App\Domain\Shared\Enums\ResultatControle;
use App\Domain\Shared\Enums\StatutAbsence;
use App\Domain\Shared\Enums\StatutVacation;
use App\Domain\Shared\Enums\TypeAgent;
use App\Domain\Shared\Support\ShiftSchedule;
use App\Models\Controle;
use App\Models\Site;
use App\Models\Vacation;

/**
 * Contrôles de présence d’une journée, regroupés par site.
 *
 * Pour chaque site : les agents qui devaient y être contrôlés (créneaux du jour,
 * hors contrôleurs), ceux qui l’ont été (présent / absent), ceux qui ne l’ont
 * pas été, et les contrôles d’agents hors planning.
 *
 * Rattachement contrôle → créneau : même règle que le pointage (créneau qui
 * contient le contrôle, sinon le plus proche dans la tolérance ; le plus récent
 * l’emporte). Les créneaux de la veille et du lendemain servent de leurres pour
 * qu’un contrôle de fin de nuit ne soit pas compté sur le mauvais jour.
 */
final class ControlesParSiteQuery
{
    public const PRESENT = 'present';

    public const ABSENT = 'absent';

    public const NON_CONTROLE = 'non_controle';

    /** Vacation « à recouvrir » sans contrôle : absence déjà connue, rien à contrôler. */
    public const ABSENCE_DECLAREE = 'absence_declaree';

    public const HORS_PLANNING = 'hors_planning';

    /**
     * @param  list<string>|null  $sitesAutorises  null = tous les sites
     * @return array<string, mixed>
     */
    public function jour(string $date, ?array $sitesAutorises = null, ?string $siteId = null): array
    {
        $jour = ShiftSchedule::dayNumber($date);
        $tolerance = (int) config('sis.controle.heure_tolerance_minutes', 60);

        $vacations = Vacation::query()
            ->with(['agent:id,nom,prenom,matricule,type', 'poste:id,nom', 'absence:id,statut'])
            ->when($sitesAutorises !== null, fn ($q) => $q->whereIn('site_id', $sitesAutorises))
            ->when($siteId, fn ($q, $v) => $q->where('site_id', $v))
            ->whereNotNull('agent_id')
            // Annulée : seulement le titulaire absent (remplacé ou absence en cours),
            // même critère que le pointage — il reste « absent » sur le site.
            ->where(fn ($q) => $q->where('statut', '!=', StatutVacation::Annulee->value)
                ->orWhere('remplacee', true)
                ->orWhereNotNull('absence_id'))
            ->whereDate('date_debut', '<=', ShiftSchedule::isoFromDayNumber($jour + 1))
            ->where(fn ($q) => $q->whereNull('date_fin')
                ->orWhereDate('date_fin', '>=', ShiftSchedule::isoFromDayNumber($jour - 1)))
            ->get()
            ->reject(fn (Vacation $v) => $v->agent?->type === TypeAgent::Controleur)
            ->reject(fn (Vacation $v) => $v->statut === StatutVacation::Annulee
                && ! $v->remplacee
                && ! self::absenceActive($v))
            ->values();

        // 1. Créneaux J-1, J, J+1 (seuls ceux de J sont restitués).
        $creneaux = [];
        foreach ($vacations as $vacation) {
            $schedule = ShiftSchedule::fromVacation($vacation);
            for ($d = $jour - 1; $d <= $jour + 1; $d++) {
                if (! $schedule->coversDay($d)) {
                    continue;
                }
                [$start, $end] = $schedule->windowOnDay($d);
                $creneaux[] = [
                    'vacation' => $vacation,
                    'day' => $d,
                    'start' => $start,
                    'end' => $end,
                    'controle' => null,
                    'nb' => 0,
                ];
            }
        }

        // 2. Contrôles des sites visibles uniquement : un contrôle ne compte que
        //    pour le site où il a été fait (jamais de fuite hors périmètre).
        $controles = Controle::query()
            ->with(['media', 'agent:id,nom,prenom,matricule', 'enregistrePar:id,nom,prenom,email', 'controleAgent:id,nom,prenom,matricule', 'poste:id,nom'])
            ->whereNotNull('controle_agent_id')
            ->where('effectue_at', '>=', ShiftSchedule::isoFromDayNumber($jour - 1))
            ->where('effectue_at', '<', ShiftSchedule::isoFromDayNumber($jour + 2))
            ->when($sitesAutorises !== null, fn ($q) => $q->whereIn('site_id', $sitesAutorises))
            ->when($siteId, fn ($q, $v) => $q->where('site_id', $v))
            ->where(function ($q) use ($vacations, $date) {
                $q->whereIn('controle_agent_id', $vacations->pluck('agent_id')->unique()->values())
                    ->orWhereDate('effectue_at', $date);
            })
            ->orderBy('effectue_at')
            ->get();

        $parAgent = [];
        foreach ($creneaux as $i => $c) {
            // Clé agent + site : un contrôle n’est rattaché qu’à un créneau du même site.
            $parAgent[$c['vacation']->agent_id.'|'.$c['vacation']->site_id][] = $i;
        }

        $horsPlanning = [];
        foreach ($controles as $controle) {
            $minute = intdiv($controle->effectue_at->getTimestamp(), 60);
            $i = self::creneauDuControle($creneaux, $parAgent[$controle->controle_agent_id.'|'.$controle->site_id] ?? [], $minute, $tolerance);
            if ($i !== null) {
                // Triés par date : le plus récent l’emporte.
                $creneaux[$i]['controle'] = $controle;
                $creneaux[$i]['nb']++;

                continue;
            }
            if ($controle->effectue_at->toDateString() === $date) {
                $horsPlanning[] = $controle;
            }
        }

        // 3. Regroupement par site.
        $sites = [];
        foreach ($creneaux as $c) {
            if ($c['day'] !== $jour) {
                continue;
            }
            /** @var Vacation $v */
            $v = $c['vacation'];
            $controle = $c['controle'];
            $etat = $controle
                ? ($controle->resultat === ResultatControle::Absent ? self::ABSENT : self::PRESENT)
                : (in_array($v->statut, [StatutVacation::ARecouvrir, StatutVacation::Annulee], true) ? self::ABSENCE_DECLAREE : self::NON_CONTROLE);

            $sites[(string) $v->site_id]['lignes'][] = [
                'vacation_id' => $v->id,
                'agent' => self::agent($v->agent),
                'poste' => $v->poste?->nom,
                'heure_debut' => substr((string) $v->heure_debut, 0, 5),
                'heure_fin' => substr((string) $v->heure_fin, 0, 5),
                'est_remplacement' => (bool) $v->est_remplacement,
                'etat' => $etat,
                'controle' => $controle ? self::controle($controle) : null,
                'nb_controles' => $c['nb'],
            ];
        }
        foreach ($horsPlanning as $controle) {
            $sites[(string) $controle->site_id]['lignes'][] = [
                'vacation_id' => null,
                'agent' => self::agent($controle->controleAgent),
                'poste' => $controle->poste?->nom,
                'heure_debut' => null,
                'heure_fin' => null,
                'est_remplacement' => false,
                'etat' => self::HORS_PLANNING,
                'controle' => self::controle($controle),
                'nb_controles' => 1,
            ];
        }

        $infos = Site::query()->withTrashed()
            ->with('zone:id,nom')
            ->whereIn('id', array_keys($sites))
            ->get(['id', 'nom', 'zone_id'])
            ->keyBy('id');

        $ordre = [self::NON_CONTROLE => 0, self::ABSENT => 1, self::HORS_PLANNING => 2, self::PRESENT => 3, self::ABSENCE_DECLAREE => 4];
        $resultat = [];
        foreach ($sites as $id => $site) {
            $lignes = collect($site['lignes']);
            $compte = fn (string $etat) => $lignes->where('etat', $etat)->count();
            $presents = $compte(self::PRESENT);
            $absents = $compte(self::ABSENT);
            $nonControles = $compte(self::NON_CONTROLE);
            $dernier = $lignes->pluck('controle.effectue_at')->filter()->max();

            $resultat[] = [
                'site_id' => $id,
                // Site supprimé (soft delete) mais vacations restantes : pas de 500.
                'site' => ($infos[$id] ?? null)?->nom ?? 'Site supprimé',
                'zone' => ($infos[$id] ?? null)?->zone?->nom,
                'a_controler' => $presents + $absents + $nonControles,
                'controles' => $presents + $absents,
                'presents' => $presents,
                'absents' => $absents,
                'non_controles' => $nonControles,
                'absences_declarees' => $compte(self::ABSENCE_DECLAREE),
                'hors_planning' => $compte(self::HORS_PLANNING),
                'dernier_controle_at' => $dernier,
                'lignes' => $lignes
                    ->sort(fn ($a, $b) => [$ordre[$a['etat']], $a['heure_debut'] ?? '99', $a['agent']['nom'] ?? '']
                        <=> [$ordre[$b['etat']], $b['heure_debut'] ?? '99', $b['agent']['nom'] ?? ''])
                    ->values()
                    ->all(),
            ];
        }

        // Les moins couverts d’abord, puis par nom.
        usort($resultat, fn ($a, $b) => [-$a['non_controles'], $a['site']] <=> [-$b['non_controles'], $b['site']]);

        $somme = fn (string $k) => array_sum(array_column($resultat, $k));

        return [
            'date' => $date,
            'totaux' => [
                'sites' => count($resultat),
                'sites_complets' => count(array_filter($resultat, fn ($s) => $s['a_controler'] > 0 && $s['non_controles'] === 0)),
                'a_controler' => $somme('a_controler'),
                'controles' => $somme('controles'),
                'presents' => $somme('presents'),
                'absents' => $somme('absents'),
                'non_controles' => $somme('non_controles'),
                'absences_declarees' => $somme('absences_declarees'),
                'hors_planning' => $somme('hors_planning'),
            ],
            'sites' => $resultat,
        ];
    }

    /** Vacation annulée à cause d’une absence encore valable (ni refusée ni annulée). */
    private static function absenceActive(Vacation $v): bool
    {
        $statut = $v->absence?->statut;

        return $statut !== null && ! in_array($statut, [StatutAbsence::Refusee, StatutAbsence::Annulee], true);
    }

    /**
     * @param  list<array{start: int, end: int}>  $creneaux
     * @param  list<int>  $indices
     */
    private static function creneauDuControle(array $creneaux, array $indices, int $minute, int $tolerance): ?int
    {
        $best = null;
        $bestDistance = null;
        foreach ($indices as $i) {
            $c = $creneaux[$i];
            $distance = $minute < $c['start'] ? $c['start'] - $minute : max(0, $minute - $c['end']);
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

    /** @return array{id: string, nom: string, prenom: string, matricule: string|null}|null */
    private static function agent($agent): ?array
    {
        return $agent ? [
            'id' => (string) $agent->id,
            'nom' => (string) $agent->nom,
            'prenom' => (string) $agent->prenom,
            'matricule' => $agent->matricule,
        ] : null;
    }

    /** @return array<string, mixed> */
    private static function controle(Controle $c): array
    {
        $photo = $c->getMedia('photos')->first();
        $par = $c->agent
            ? trim($c->agent->prenom.' '.$c->agent->nom)
            : ($c->enregistrePar
                ? (trim(($c->enregistrePar->prenom ?? '').' '.($c->enregistrePar->nom ?? '')) ?: $c->enregistrePar->email)
                : null);

        return [
            'id' => $c->id,
            'effectue_at' => $c->effectue_at?->toIso8601String(),
            'resultat' => $c->resultat?->value ?? self::PRESENT,
            'controleur' => $par,
            'controleur_matricule' => $c->agent?->matricule,
            'siege' => $c->agent === null && $c->enregistrePar !== null,
            'commentaire' => $c->commentaire,
            'photo' => $photo ? ['url' => $photo->getUrl(), 'thumb' => $photo->getUrl('thumb')] : null,
        ];
    }
}
