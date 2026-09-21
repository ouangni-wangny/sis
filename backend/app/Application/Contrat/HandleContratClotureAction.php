<?php

namespace App\Application\Contrat;

use App\Domain\Shared\Enums\StatutAgent;
use App\Domain\Shared\Enums\StatutVacation;
use App\Models\Agent;
use App\Models\Contrat;
use App\Models\Vacation;

final class HandleContratClotureAction
{
    private const CLOSED_STATUTS = ['termine', 'resilie'];

    public function execute(Contrat $contrat, ?string $previousStatut = null): void
    {
        if (! in_array($contrat->statut, self::CLOSED_STATUTS, true)) {
            return;
        }

        if ($previousStatut !== null && in_array($previousStatut, self::CLOSED_STATUTS, true)) {
            return;
        }

        $agent = $contrat->agent;
        if (! $agent) {
            return;
        }

        $this->retirerPerimetres($agent);
        $this->archiverSiOperationnel($agent);
        $this->marquerVacationsARecouvrir($agent);
    }

    private function retirerPerimetres(Agent $agent): void
    {
        $agent->perimetres()->delete();
    }

    private function archiverSiOperationnel(Agent $agent): void
    {
        if (! in_array($agent->statut, [StatutAgent::Disponible, StatutAgent::EnActivite], true)) {
            return;
        }

        $agent->update(['statut' => StatutAgent::Archive]);
    }

    private function marquerVacationsARecouvrir(Agent $agent): void
    {
        $today = now()->toDateString();

        Vacation::query()
            ->where('agent_id', $agent->id)
            ->whereIn('statut', [
                StatutVacation::Planifiee->value,
                StatutVacation::EnCours->value,
            ])
            ->where(function ($q) use ($today) {
                $q->whereNull('date_fin')->orWhereDate('date_fin', '>=', $today);
            })
            ->update(['statut' => StatutVacation::ARecouvrir->value]);
    }
}
