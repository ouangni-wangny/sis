<?php

namespace App\Domain\Paie;

use App\Domain\Shared\Enums\StatutBulletinPaie;
use App\Models\Agent;
use App\Models\AvanceSalaire;
use App\Models\BulletinPaie;
use App\Models\Contrat;

/** Salaire du mois d’un agent, avances données et reste à payer. */
final class SituationSalaireMois
{
    /**
     * Le net vient du bulletin du mois s’il existe, sinon du contrat valide (estimation).
     * Un net à 0 est inconnu : les salaires peuvent n’être saisis qu’au bulletin.
     *
     * @return array{
     *   salaire_net: float|null,
     *   source: 'bulletin'|'contrat'|null,
     *   avances_total: float,
     *   reste_a_payer: float|null,
     *   bulletin_paye: bool,
     * }
     */
    public static function pour(Agent $agent, int $mois, int $annee): array
    {
        $bulletin = BulletinPaie::query()
            ->where('agent_id', $agent->id)
            ->whereHas('periodePaie', fn ($q) => $q->where('mois', $mois)->where('annee', $annee))
            ->first();

        $avances = AvanceSalaire::totalPour($agent->id, $mois, $annee);
        $paye = $bulletin?->statut === StatutBulletinPaie::Paye;

        $source = $bulletin ? 'bulletin' : 'contrat';
        $net = $bulletin
            ? (float) $bulletin->salaire_net
            : (float) (Contrat::query()
                ->valide()
                ->where('agent_id', $agent->id)
                ->orderByDesc('date_debut')
                ->value('salaire_net') ?? 0);

        if ($net <= 0) {
            return [
                'salaire_net' => null,
                'source' => null,
                'avances_total' => $avances,
                'reste_a_payer' => null,
                'bulletin_paye' => $paye,
            ];
        }

        return [
            'salaire_net' => round($net, 2),
            'source' => $source,
            'avances_total' => $avances,
            'reste_a_payer' => $paye ? 0.0 : max(0.0, round($net - $avances, 2)),
            'bulletin_paye' => $paye,
        ];
    }
}
