<?php

namespace App\Application\Planning;

use App\Domain\Shared\Enums\StatutPlanningSemaine;
use App\Domain\Shared\Enums\StatutVacation;
use App\Domain\Shared\Support\PosteEffectifRules;
use App\Domain\Shared\Support\ShiftInterval;
use App\Models\PlanningSemaine;
use App\Models\Poste;
use App\Models\Vacation;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Trous à combler par les rondiers sur une semaine : pour chaque poste, jour et
 * quart, l’écart entre l’effectif nominal du poste (agents requis / alternance)
 * et les vacations tenues.
 *
 * Contrairement à la couverture, l’effectif attendu ne vient pas du modèle
 * semaine : un jour de repos du titulaire (aucun créneau prévu) est un trou.
 *
 * Motifs :
 * - « absence » : vacation « à recouvrir » (agent absent) → remplacement ;
 * - « repos » / « vide » : place non tenue → affectation d’un rondier.
 */
final class TrousRondiersQuery
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function semaine(string $lundi, ?string $siteId = null, ?string $zoneId = null): array
    {
        $debut = Carbon::parse($lundi, 'Africa/Abidjan')->startOfWeek(Carbon::MONDAY)->startOfDay();
        $jours = collect(range(0, 6))->map(fn (int $i) => $debut->copy()->addDays($i));
        $iso = PlanningSemaine::isoFromDate($debut);

        $postes = Poste::query()
            ->with('site:id,nom,zone_id')
            // Postes actifs d’un site existant, avec un effectif à tenir.
            ->whereHas('site')
            ->where('agents_requis', '>', 0)
            ->when($siteId, fn ($q, $v) => $q->where('site_id', $v))
            ->when($zoneId, fn ($q, $v) => $q->whereHas('site', fn ($s) => $s->where('zone_id', $v)))
            ->get();
        if ($postes->isEmpty()) {
            return [];
        }
        $posteIds = $postes->pluck('id');

        $vacations = Vacation::query()
            ->with('agent:id,nom,prenom,matricule')
            ->whereIn('poste_id', $posteIds)
            ->where('statut', '!=', StatutVacation::Annulee->value)
            // Toute vacation qui touche la semaine, y compris sur période.
            ->whereDate('date_debut', '<=', $jours->last()->toDateString())
            ->where(fn ($q) => $q->whereNull('date_fin')
                ->orWhereDate('date_fin', '>=', $debut->copy()->subDay()->toDateString()))
            ->get()
            ->groupBy('poste_id');

        $templates = PlanningSemaine::query()
            ->with('creneaux.agent:id,nom,prenom,grade_id', 'creneaux.agent.grade:id,est_rondier')
            ->whereIn('poste_id', $posteIds)
            ->where('annee_iso', $iso['annee_iso'])
            ->where('semaine_iso', $iso['semaine_iso'])
            ->where('statut', StatutPlanningSemaine::Publiee)
            ->get()
            ->keyBy('poste_id');

        $trous = [];
        foreach ($postes as $poste) {
            /** @var Collection<int, Vacation> $vacs */
            $vacs = $vacations->get($poste->id, collect());
            $tenues = $vacs->filter(fn (Vacation $v) => $v->statut !== StatutVacation::ARecouvrir);
            $aRecouvrir = $vacs->filter(fn (Vacation $v) => $v->statut === StatutVacation::ARecouvrir);
            $windows = PosteEffectifRules::quartWindows($poste);

            $quarts = $windows !== null
                ? [
                    'jour' => [$windows['jour']['heure_debut'], $windows['jour']['heure_fin']],
                    'nuit' => [$windows['nuit']['heure_debut'], $windows['nuit']['heure_fin']],
                ]
                : ['_simple' => [
                    ShiftInterval::hm((string) ($poste->heure_debut ?? '07:00')),
                    ShiftInterval::hm((string) ($poste->heure_fin ?? '19:00')),
                ]];

            foreach ($jours as $jour) {
                $date = $jour->toDateString();
                foreach ($quarts as $quart => [$hDebut, $hFin]) {
                    $nominal = $quart === '_simple'
                        ? PosteEffectifRules::capaciteSimple($poste)
                        : PosteEffectifRules::capaciteParQuart($poste);
                    $tenu = $quart === '_simple'
                        ? $tenues->filter(fn (Vacation $v) => PosteEffectifRules::vacationCoversPlanningDay($v, $jour))
                            ->pluck('agent_id')->unique()->count()
                        : PosteEffectifRules::countOnQuart($tenues, $poste, $jour, $quart);
                    $manquant = $nominal - $tenu;
                    if ($manquant <= 0) {
                        continue;
                    }

                    $base = [
                        'date' => $date,
                        'poste_id' => (string) $poste->id,
                        'poste' => $poste->nom,
                        'site_id' => (string) $poste->site_id,
                        'site' => $poste->site?->nom,
                        'zone_id' => $poste->site?->zone_id ? (string) $poste->site->zone_id : null,
                        'quart' => $quart === '_simple' ? null : $quart,
                        'heure_debut' => $hDebut,
                        'heure_fin' => $hFin,
                    ];

                    $absences = $aRecouvrir->filter(function (Vacation $v) use ($poste, $jour, $quart) {
                        if (! PosteEffectifRules::vacationCoversPlanningDay($v, $jour)) {
                            return false;
                        }

                        return $quart === '_simple'
                            || in_array($quart, PosteEffectifRules::quartsTouched($poste, $jour, (string) $v->heure_debut, (string) $v->heure_fin), true);
                    })->values();

                    foreach ($absences->take($manquant) as $v) {
                        $trous[] = $base + [
                            'motif' => 'absence',
                            'vacation_id' => (string) $v->id,
                            'heure_debut' => ShiftInterval::hm((string) $v->heure_debut),
                            'heure_fin' => ShiftInterval::hm((string) $v->heure_fin),
                            'titulaire' => $v->agent ? trim($v->agent->prenom.' '.$v->agent->nom) : null,
                        ];
                        $manquant--;
                    }

                    if ($manquant <= 0) {
                        continue;
                    }
                    $repos = self::titulairesAuRepos($templates->get($poste->id), (int) $jour->dayOfWeekIso, $tenues, $jour);
                    for ($i = 0; $i < $manquant; $i++) {
                        $trous[] = $base + [
                            'motif' => isset($repos[$i]) ? 'repos' : 'vide',
                            'vacation_id' => null,
                            'titulaire' => $repos[$i] ?? null,
                        ];
                    }
                }
            }
        }

        usort($trous, fn (array $a, array $b) => [$a['date'], $a['site'] ?? '', $a['poste'], $a['heure_debut']]
            <=> [$b['date'], $b['site'] ?? '', $b['poste'], $b['heure_debut']]);

        return $trous;
    }

    /**
     * Titulaires du modèle publié sans créneau ce jour (repos), hors rondiers.
     *
     * @param  Collection<int, Vacation>  $tenues
     * @return list<string>
     */
    private static function titulairesAuRepos(?PlanningSemaine $template, int $jourIso, Collection $tenues, Carbon $jour): array
    {
        if (! $template) {
            return [];
        }
        $presents = $tenues->filter(fn (Vacation $v) => PosteEffectifRules::vacationCoversPlanningDay($v, $jour))
            ->pluck('agent_id')->map(fn ($id) => (string) $id)->all();

        return $template->creneaux
            ->filter(fn ($c) => $c->agent && ! $c->agent->grade?->est_rondier)
            ->groupBy('agent_id')
            ->reject(fn ($creneaux, $agentId) => $creneaux->contains('jour', $jourIso) || in_array((string) $agentId, $presents, true))
            ->map(fn ($creneaux) => trim($creneaux->first()->agent->prenom.' '.$creneaux->first()->agent->nom))
            ->values()
            ->all();
    }
}
