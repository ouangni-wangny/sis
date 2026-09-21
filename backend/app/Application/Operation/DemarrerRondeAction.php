<?php

namespace App\Application\Operation;

use App\Application\Shared\AssertAgentAssignable;
use App\Domain\Shared\Enums\StatutRonde;
use App\Domain\Shared\Enums\StatutVacation;
use App\Domain\Shared\Enums\TypeAgent;
use App\Domain\Shared\Exceptions\RondeDejaDemarreeException;
use App\Models\Ronde;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DemarrerRondeAction
{
    public function execute(Ronde $ronde): Ronde
    {
        return DB::transaction(function () use ($ronde) {
            $ronde->loadMissing(['site', 'agent', 'vacation']);

            if ($ronde->statut === StatutRonde::EnCours) {
                throw new RondeDejaDemarreeException;
            }

            if ($ronde->statut !== StatutRonde::Planifiee) {
                throw ValidationException::withMessages([
                    'statut' => 'Seule une ronde planifiée peut être démarrée.',
                ]);
            }

            AssertAgentAssignable::execute(
                (string) $ronde->agent_id,
                TypeAgent::Controleur,
            );

            if ($ronde->vacation_id) {
                $vacation = $ronde->vacation;
                if (! $vacation || in_array($vacation->statut, [StatutVacation::Annulee, StatutVacation::Terminee], true)) {
                    throw ValidationException::withMessages([
                        'vacation_id' => 'La vacation liée est invalide (annulée ou terminée).',
                    ]);
                }
            }

            if ($ronde->site->checkpoints()->count() < 1) {
                throw ValidationException::withMessages([
                    'site_id' => 'Impossible de démarrer : ce site n’a aucun checkpoint.',
                ]);
            }

            $exists = Ronde::query()
                ->where('agent_id', $ronde->agent_id)
                ->where('statut', StatutRonde::EnCours)
                ->where('id', '!=', $ronde->id)
                ->exists();

            if ($exists) {
                throw new RondeDejaDemarreeException('Une autre ronde est déjà en cours pour cet agent.');
            }

            $ronde->update([
                'statut' => StatutRonde::EnCours,
                'demarree_at' => now(),
                'progression' => 0,
            ]);

            $checkpoints = $ronde->site->checkpoints()->orderBy('ordre')->get();
            foreach ($checkpoints as $cp) {
                $ronde->rondeCheckpoints()->firstOrCreate(
                    ['checkpoint_id' => $cp->id],
                    ['valide' => false]
                );
            }

            return $ronde->fresh(['rondeCheckpoints.checkpoint', 'agent', 'site']);
        });
    }
}
