<?php

namespace App\Application\Rh;

use App\Domain\Shared\Enums\StatutAbsence;
use App\Models\Absence;
use Illuminate\Support\Facades\DB;

final class DeleteAbsenceAction
{
    public function __construct(
        private readonly UpsertAbsenceAction $upsertAbsenceAction,
    ) {}

    public function execute(Absence $absence): AbsenceOperationResult
    {
        return DB::transaction(function () use ($absence) {
            $restaurees = 0;

            if ($absence->statut === StatutAbsence::Approuvee) {
                $restaurees = $this->upsertAbsenceAction->restaurerVacationsLiees($absence);
                $this->upsertAbsenceAction->restoreAgentStatutIfNeeded(
                    $absence->agent_id,
                    $absence->type,
                    $absence->date_debut->format('Y-m-d'),
                    $absence->date_fin->format('Y-m-d'),
                    $absence->id,
                );
            }

            $absence->delete();

            return new AbsenceOperationResult($absence, 0, $restaurees);
        });
    }
}
