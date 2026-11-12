<?php

namespace App\Application\Reporting;

use App\Domain\Shared\Enums\StatutPlanningSemaine;
use App\Domain\Shared\Support\PosteEffectifRules;
use App\Models\PlanningSemaine;
use App\Models\Poste;
use App\Models\Vacation;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final class GetPosteCoverageAction
{
    public function execute(string $date, ?string $siteId = null): Collection
    {
        $target = Carbon::parse($date)->startOfDay();

        $postes = Poste::query()
            ->with('site')
            ->when($siteId, fn ($q, $v) => $q->where('site_id', $v))
            ->get();

        $posteIds = $postes->pluck('id');

        $vacationsByPoste = Vacation::query()
            ->whereIn('poste_id', $posteIds)
            ->whereNotIn('statut', ['annulee', 'a_recouvrir'])
            ->whereDate('date_debut', '<=', $target->format('Y-m-d'))
            ->get()
            ->filter(fn (Vacation $v) => PosteEffectifRules::vacationCoversPlanningDay($v, $target))
            ->groupBy('poste_id');

        $iso = PlanningSemaine::isoFromDate($target);
        $templatesByPoste = PlanningSemaine::query()
            ->whereIn('poste_id', $posteIds)
            ->where('annee_iso', $iso['annee_iso'])
            ->where('semaine_iso', $iso['semaine_iso'])
            ->where('statut', StatutPlanningSemaine::Publiee)
            ->with('creneaux')
            ->get()
            ->keyBy('poste_id');

        return $postes->map(function (Poste $poste) use ($vacationsByPoste, $target, $templatesByPoste) {
            $posteVacations = $vacationsByPoste->get($poste->id, collect());
            $windows = PosteEffectifRules::quartWindows($poste);

            if ($windows !== null) {
                return $this->coverage24h($poste, $posteVacations, $target, $windows, $templatesByPoste);
            }

            $planifies = $posteVacations->count();
            $capacite = PosteEffectifRules::capaciteSimplePourJour($poste, $target, $templatesByPoste);
            $manquant = max(0, $capacite - $planifies);

            // sur_effectif = trop vs capacité du jour (template ou agents_requis).
            $statut = match (true) {
                $capacite === 0 && $planifies === 0 => 'ok',
                $planifies === 0 => 'non_couvert',
                $planifies > $capacite => 'sur_effectif',
                $manquant === 0 => 'ok',
                default => 'sous_effectif',
            };

            return [
                'poste_id' => $poste->id,
                'nom' => $poste->nom,
                'agents_requis' => $poste->agents_requis,
                'capacite_jour' => $capacite,
                'site' => $this->siteData($poste),
                'planifies' => $planifies,
                'manquant' => $manquant,
                'statut' => $statut,
                'couverture_24h' => false,
            ];
        })->values();
    }

    /**
     * @param  array{jour: array{heure_debut: string, heure_fin: string}, nuit: array{heure_debut: string, heure_fin: string}}  $windows
     * @param  Collection<string, PlanningSemaine>  $templatesByPoste
     */
    private function coverage24h(
        Poste $poste,
        Collection $posteVacations,
        Carbon $target,
        array $windows,
        Collection $templatesByPoste,
    ): array {
        $capJour = PosteEffectifRules::capaciteParQuartPourJour($poste, $target, 'jour', $templatesByPoste);
        $capNuit = PosteEffectifRules::capaciteParQuartPourJour($poste, $target, 'nuit', $templatesByPoste);
        $planifiesJour = PosteEffectifRules::countOnQuart(
            $posteVacations,
            $poste,
            $target,
            'jour',
        );
        $planifiesNuit = PosteEffectifRules::countOnQuart(
            $posteVacations,
            $poste,
            $target,
            'nuit',
        );

        $hasTemplate = PosteEffectifRules::templatePubliePourJour($poste, $target, $templatesByPoste) !== null;
        $capaciteJournee = $hasTemplate
            ? PosteEffectifRules::capaciteSimplePourJour($poste, $target, $templatesByPoste)
            : max(1, (int) $poste->agents_requis);

        // Places tenues pour la couverture ; le sur-effectif se juge en personnes :
        // qui enchaîne jour et nuit couvre deux places sans compter deux fois.
        $personnes = $posteVacations
            ->filter(fn ($v) => PosteEffectifRules::vacationCoversPlanningDay($v, $target))
            ->pluck('agent_id')
            ->unique()
            ->count();
        $surQuart = $planifiesJour > $capJour || $planifiesNuit > $capNuit;
        $surEffectif = $personnes > $capaciteJournee || $surQuart;
        $plafondPlaces = $hasTemplate
            ? max($capJour + $capNuit, 1)
            : max(1, (int) $poste->agents_requis);
        $planifies = $surEffectif
            ? $personnes
            : min($planifiesJour + $planifiesNuit, $plafondPlaces);
        $manquantPlaces = max(0, $capJour - $planifiesJour) + max(0, $capNuit - $planifiesNuit);
        $quartsComplets = ($capJour === 0 || $planifiesJour >= $capJour)
            && ($capNuit === 0 || $planifiesNuit >= $capNuit);
        $aucuneAttente = $hasTemplate && $capJour === 0 && $capNuit === 0;

        $statut = match (true) {
            $aucuneAttente && $personnes === 0 => 'ok',
            $planifiesJour + $planifiesNuit === 0 => 'non_couvert',
            $surEffectif => 'sur_effectif',
            $manquantPlaces === 0 && $quartsComplets => 'ok',
            default => 'sous_effectif',
        };

        return [
            'poste_id' => $poste->id,
            'nom' => $poste->nom,
            'agents_requis' => $poste->agents_requis,
            'capacite_jour' => $capaciteJournee,
            'site' => $this->siteData($poste),
            'planifies' => $planifies,
            'manquant' => $manquantPlaces,
            'statut' => $statut,
            'couverture_24h' => true,
            'jour' => [
                'planifies' => $planifiesJour,
                'manquant' => max(0, $capJour - $planifiesJour),
            ],
            'nuit' => [
                'planifies' => $planifiesNuit,
                'manquant' => max(0, $capNuit - $planifiesNuit),
            ],
        ];
    }

    private function siteData(Poste $poste): ?array
    {
        return $poste->site ? ['id' => $poste->site->id, 'nom' => $poste->site->nom] : null;
    }
}
