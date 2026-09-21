<?php

namespace App\Application\Operation;

use App\Application\Shared\AssertAgentAssignable;
use App\Domain\Shared\Enums\StatutAgent;
use App\Domain\Shared\Enums\StatutVacation;
use App\Domain\Shared\Exceptions\VacationConflitException;
use App\Models\Agent;
use App\Models\Poste;
use App\Models\Vacation;
use Illuminate\Support\Facades\DB;

final class CreateVacationAction
{
    public function __construct(
        private DetecterConflitsVacationAction $detecter,
        private DetecterAbsenceAgentAction $absences,
    ) {}

    public function execute(array $data, bool $wrapInTransaction = true): Vacation
    {
        if (! $wrapInTransaction) {
            return $this->createOne($data);
        }

        return DB::transaction(fn () => $this->createOne($data));
    }

    private function createOne(array $data): Vacation
    {
        $schedule = NormalizeVacationSchedule::apply(
            $data['date_debut'],
            $data['heure_debut'],
            $data['heure_fin'],
            $data['date_fin'] ?? null,
        );

        $data['date_debut'] = $schedule['date_debut'];
        $data['date_fin'] = $schedule['date_fin'];
        $data['heure_debut'] = $schedule['heure_debut'];
        $data['heure_fin'] = $schedule['heure_fin'];
        $data['statut'] = StatutVacation::Planifiee->value;

        // Verrou agent (+ poste) pour éviter les doubles créations concurrentes.
        Agent::query()->whereKey($data['agent_id'])->lockForUpdate()->firstOrFail();
        if (! empty($data['poste_id'])) {
            Poste::query()->whereKey($data['poste_id'])->lockForUpdate()->first();
        }

        AssertAgentAssignable::execute($data['agent_id'], referenceDate: $data['date_debut']);
        AssertAgentAssignable::posteBelongsToSite(
            $data['poste_id'] ?? null,
            $data['site_id'],
        );

        $this->absences->assertDisponible(
            $data['agent_id'],
            $data['date_debut'],
            $data['heure_debut'],
            $data['heure_fin'],
            $data['date_fin'],
        );

        $conflits = $this->detecter->execute(
            $data['agent_id'],
            $data['date_debut'],
            $data['heure_debut'],
            $data['heure_fin'],
            $data['date_fin'],
        );

        $reposOptions = [
            'poste_id' => $data['poste_id'] ?? null,
        ];

        $reposBlockers = AssertVacationDureeLegale::findReposInsuffisant(
            $data['agent_id'],
            $data['date_debut'],
            $data['heure_debut'],
            $data['heure_fin'],
            $data['date_fin'],
            null,
            $reposOptions,
        );

        $blockers = $conflits->concat($reposBlockers)->unique('id')->values();
        $annulerConflits = (bool) ($data['annuler_conflits'] ?? false);

        if ($blockers->isNotEmpty()) {
            if ($annulerConflits) {
                $result = AnnulerVacationsBloquantes::execute($blockers);
                if ($result['skipped_ronde'] > 0) {
                    throw new VacationConflitException(
                        sprintf(
                            'Impossible d’annuler %d vacation(s) (ronde en cours).',
                            $result['skipped_ronde'],
                        ),
                        (string) $blockers->first()->id,
                    );
                }
            } elseif ($conflits->isNotEmpty()) {
                throw new VacationConflitException(
                    'Cet agent a déjà une vacation sur ce créneau (même site ou autre site).',
                    (string) $conflits->first()->id,
                );
            } else {
                // Repos insuffisant : message détaillé via assert.
                AssertVacationDureeLegale::execute(
                    $data['agent_id'],
                    $data['date_debut'],
                    $data['heure_debut'],
                    $data['heure_fin'],
                    $data['date_fin'],
                    null,
                    $reposOptions,
                );
            }
        }

        AssertVacationDureeLegale::execute(
            $data['agent_id'],
            $data['date_debut'],
            $data['heure_debut'],
            $data['heure_fin'],
            $data['date_fin'],
            null,
            $reposOptions,
        );

        AssertPosteEffectifDisponible::execute(
            $data['poste_id'] ?? null,
            $data['date_debut'],
            $data['heure_debut'],
            $data['heure_fin'],
            $data['date_fin'],
        );

        unset($data['annuler_conflits']);

        $vacation = Vacation::query()->create($data);

        Agent::query()
            ->whereKey($data['agent_id'])
            ->update(['statut' => StatutAgent::EnActivite]);

        return $vacation->load(['agent', 'site', 'poste']);
    }
}
