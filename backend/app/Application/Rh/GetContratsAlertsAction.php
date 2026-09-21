<?php

namespace App\Application\Rh;

use App\Domain\Contrat\ContratSurveillanceQuery;
use App\Domain\Shared\Enums\TypeContrat;
use App\Models\Contrat;
use Carbon\Carbon;

final class GetContratsAlertsAction
{
    /**
     * @return list<array{
     *   contrat_id: string,
     *   agent_id: string,
     *   agent_nom: string,
     *   matricule: string,
     *   type: string,
     *   reference: string|null,
     *   alerte: string,
     *   date_reference: string,
     *   jours_restants: int
     * }>
     */
    public function execute(int $joursEssai = 14, int $joursFin = 30): array
    {
        $today = now()->startOfDay();
        $alerts = [];

        $contrats = ContratSurveillanceQuery::apply(
            Contrat::query()->with('agent'),
            $joursEssai,
            $joursFin,
            $today,
        )->get();

        foreach ($contrats as $contrat) {
            if (! $contrat->agent) {
                continue;
            }

            $agentLabel = trim($contrat->agent->prenom.' '.$contrat->agent->nom);

            if ($contrat->periode_essai_mois > 0) {
                $finEssai = $contrat->date_debut->copy()->addMonths($contrat->periode_essai_mois);
                $jours = $today->diffInDays($finEssai, false);

                if ($jours >= 0 && $jours <= $joursEssai) {
                    $alerts[] = [
                        'contrat_id' => $contrat->id,
                        'agent_id' => $contrat->agent_id,
                        'agent_nom' => $agentLabel,
                        'matricule' => $contrat->agent->matricule,
                        'type' => $this->typeValue($contrat->type),
                        'reference' => $contrat->reference,
                        'alerte' => 'fin_essai',
                        'date_reference' => $finEssai->toDateString(),
                        'jours_restants' => (int) $jours,
                    ];
                }
            }

            if ($contrat->date_fin) {
                $jours = $today->diffInDays($contrat->date_fin, false);

                if ($jours >= 0 && $jours <= $joursFin) {
                    $alerts[] = [
                        'contrat_id' => $contrat->id,
                        'agent_id' => $contrat->agent_id,
                        'agent_nom' => $agentLabel,
                        'matricule' => $contrat->agent->matricule,
                        'type' => $this->typeValue($contrat->type),
                        'reference' => $contrat->reference,
                        'alerte' => 'fin_contrat',
                        'date_reference' => $contrat->date_fin->toDateString(),
                        'jours_restants' => (int) $jours,
                    ];
                }
            }
        }

        usort($alerts, fn ($a, $b) => $a['jours_restants'] <=> $b['jours_restants']);

        return $alerts;
    }

    private function typeValue(mixed $type): string
    {
        if ($type instanceof TypeContrat) {
            return $type->value;
        }

        return (string) $type;
    }
}
