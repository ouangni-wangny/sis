<?php

namespace App\Application\Reporting;

use App\Domain\Shared\Support\PosteEffectifRules;
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

        return $postes->map(function (Poste $poste) use ($vacationsByPoste, $target) {
            $posteVacations = $vacationsByPoste->get($poste->id, collect());
            $windows = PosteEffectifRules::quartWindows($poste);

            if ($windows !== null) {
                return $this->coverage24h($poste, $posteVacations, $target, $windows);
            }

            $planifies = $posteVacations->count();
            $capacite = PosteEffectifRules::capaciteSimple($poste);
            $manquant = max(0, $capacite - $planifies);

            // sur_effectif = anomalie héritée uniquement (ne doit plus être créable).
            $statut = match (true) {
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
     */
    private function coverage24h(
        Poste $poste,
        Collection $posteVacations,
        Carbon $target,
        array $windows,
    ): array {
        $capQuart = PosteEffectifRules::capaciteParQuart($poste);
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

        $planifies = $planifiesJour + $planifiesNuit;
        $manquant = max(0, $poste->agents_requis - $planifies);
        $quartsComplets = $planifiesJour > 0 && $planifiesNuit > 0;
        $surQuart = $planifiesJour > $capQuart || $planifiesNuit > $capQuart;

        $statut = match (true) {
            $planifies === 0 => 'non_couvert',
            $planifies > $poste->agents_requis || $surQuart => 'sur_effectif',
            $manquant === 0 && $quartsComplets => 'ok',
            default => 'sous_effectif',
        };

        return [
            'poste_id' => $poste->id,
            'nom' => $poste->nom,
            'agents_requis' => $poste->agents_requis,
            'site' => $this->siteData($poste),
            'planifies' => $planifies,
            'manquant' => $manquant === 0 && ! $quartsComplets
                ? ($planifiesJour === 0 ? 1 : 0) + ($planifiesNuit === 0 ? 1 : 0)
                : $manquant,
            'statut' => $statut,
            'couverture_24h' => true,
            'jour' => [
                'planifies' => $planifiesJour,
                'manquant' => max(0, $capQuart - $planifiesJour),
            ],
            'nuit' => [
                'planifies' => $planifiesNuit,
                'manquant' => max(0, $capQuart - $planifiesNuit),
            ],
        ];
    }

    private function siteData(Poste $poste): ?array
    {
        return $poste->site ? ['id' => $poste->site->id, 'nom' => $poste->site->nom] : null;
    }
}
