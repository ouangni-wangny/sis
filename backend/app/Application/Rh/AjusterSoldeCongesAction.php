<?php

namespace App\Application\Rh;

use App\Domain\Shared\Enums\TypeAbsence;
use App\Models\Absence;
use App\Models\Agent;
use App\Models\SoldeCongesMouvement;
use App\Models\User;
use Carbon\Carbon;

final class AjusterSoldeCongesAction
{
    public function deduirePourAbsence(Absence $absence, ?User $user = null): void
    {
        if ($absence->type !== TypeAbsence::Conge) {
            return;
        }

        $agent = Agent::query()->findOrFail($absence->agent_id);
        $jours = $this->compterJoursOuvres(
            $absence->date_debut->format('Y-m-d'),
            $absence->date_fin->format('Y-m-d'),
        );

        if ($jours <= 0) {
            return;
        }

        $nouveauSolde = (float) $agent->solde_conges_jours - $jours;
        $agent->update(['solde_conges_jours' => $nouveauSolde]);

        SoldeCongesMouvement::query()->create([
            'agent_id' => $agent->id,
            'absence_id' => $absence->id,
            'type' => 'deduction_absence',
            'jours' => -$jours,
            'solde_apres' => $nouveauSolde,
            'motif' => 'Absence congé approuvée',
            'user_id' => $user?->id,
        ]);
    }

    public function restaurerPourAbsence(Absence $absence, ?User $user = null): void
    {
        $mouvement = SoldeCongesMouvement::query()
            ->where('absence_id', $absence->id)
            ->where('type', 'deduction_absence')
            ->first();

        if (! $mouvement) {
            return;
        }

        $agent = Agent::query()->findOrFail($absence->agent_id);
        $jours = abs((float) $mouvement->jours);
        $nouveauSolde = (float) $agent->solde_conges_jours + $jours;
        $agent->update(['solde_conges_jours' => $nouveauSolde]);

        SoldeCongesMouvement::query()->create([
            'agent_id' => $agent->id,
            'absence_id' => $absence->id,
            'type' => 'restauration_absence',
            'jours' => $jours,
            'solde_apres' => $nouveauSolde,
            'motif' => 'Annulation / refus absence congé',
            'user_id' => $user?->id,
        ]);

        $mouvement->delete();
    }

    public function accorderAnnuel(Agent $agent, ?float $jours = null, ?User $user = null): void
    {
        $jours = $jours ?? (float) $agent->conges_acquis_annuel;
        $nouveauSolde = (float) $agent->solde_conges_jours + $jours;
        $agent->update(['solde_conges_jours' => $nouveauSolde]);

        SoldeCongesMouvement::query()->create([
            'agent_id' => $agent->id,
            'type' => 'acquisition_annuelle',
            'jours' => $jours,
            'solde_apres' => $nouveauSolde,
            'motif' => 'Acquisition annuelle',
            'user_id' => $user?->id,
        ]);
    }

    private function compterJoursOuvres(string $debut, string $fin): int
    {
        $start = Carbon::parse($debut);
        $end = Carbon::parse($fin);
        $count = 0;

        while ($start->lte($end)) {
            if (! $start->isWeekend()) {
                $count++;
            }
            $start->addDay();
        }

        return max(1, $count);
    }
}
