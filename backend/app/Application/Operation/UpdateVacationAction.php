<?php

namespace App\Application\Operation;

use App\Application\Shared\AssertAgentAssignable;
use App\Domain\Shared\Enums\StatutAgent;
use App\Domain\Shared\Enums\StatutRonde;
use App\Domain\Shared\Enums\StatutVacation;
use App\Domain\Shared\Exceptions\VacationConflitException;
use App\Models\Agent;
use App\Models\Poste;
use App\Models\Ronde;
use App\Models\Vacation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateVacationAction
{
    public function __construct(
        private DetecterConflitsVacationAction $detecter,
        private DetecterAbsenceAgentAction $absences,
    ) {}

    public function execute(Vacation $vacation, array $data): Vacation
    {
        return DB::transaction(function () use ($vacation, $data) {
            $vacation = Vacation::query()->whereKey($vacation->id)->lockForUpdate()->firstOrFail();

            $agentId = $data['agent_id'] ?? $vacation->agent_id;
            $siteId = $data['site_id'] ?? $vacation->site_id;
            $posteId = array_key_exists('poste_id', $data)
                ? $data['poste_id']
                : $vacation->poste_id;
            $dateDebut = $data['date_debut'] ?? $vacation->date_debut->format('Y-m-d');
            $dateFin = array_key_exists('date_fin', $data)
                ? $data['date_fin']
                : $vacation->date_fin?->format('Y-m-d');
            $heureDebut = $data['heure_debut'] ?? substr((string) $vacation->heure_debut, 0, 5);
            $heureFin = $data['heure_fin'] ?? substr((string) $vacation->heure_fin, 0, 5);
            $statut = $data['statut'] ?? $vacation->statut->value;

            $schedule = NormalizeVacationSchedule::apply(
                $dateDebut,
                $heureDebut,
                $heureFin,
                $dateFin,
            );
            $dateDebut = $schedule['date_debut'];
            $dateFin = $schedule['date_fin'];
            $heureDebut = $schedule['heure_debut'];
            $heureFin = $schedule['heure_fin'];

            $scheduleTouched = array_key_exists('date_debut', $data)
                || array_key_exists('date_fin', $data)
                || array_key_exists('heure_debut', $data)
                || array_key_exists('heure_fin', $data);

            $overnightNeedsFix = $dateFin !== null
                && $vacation->date_fin?->format('Y-m-d') !== $dateFin
                && $heureFin < $heureDebut;

            if ($scheduleTouched || $overnightNeedsFix) {
                $data['date_debut'] = $dateDebut;
                $data['date_fin'] = $dateFin;
                $data['heure_debut'] = $heureDebut;
                $data['heure_fin'] = $heureFin;
            }

            Agent::query()->whereKey($agentId)->lockForUpdate()->firstOrFail();
            if ($posteId) {
                Poste::query()->whereKey($posteId)->lockForUpdate()->first();
            }

            $hasOpenRonde = Ronde::query()
                ->where('vacation_id', $vacation->id)
                ->whereIn('statut', [StatutRonde::Planifiee, StatutRonde::EnCours])
                ->exists();

            if ($hasOpenRonde && $statut === StatutVacation::Annulee->value) {
                throw ValidationException::withMessages([
                    'statut' => 'Impossible d’annuler : une ronde liée est encore planifiée ou en cours.',
                ]);
            }

            // Annulation / trou : pas de contrôles d’affectation (l’agent peut être indisponible).
            $skipPlanningGuards = in_array($statut, [
                StatutVacation::Annulee->value,
                StatutVacation::ARecouvrir->value,
            ], true);

            if (! $skipPlanningGuards) {
                AssertAgentAssignable::execute($agentId, referenceDate: $dateDebut);
                AssertAgentAssignable::posteBelongsToSite($posteId, $siteId);

                $this->absences->assertDisponible(
                    $agentId,
                    $dateDebut,
                    $heureDebut,
                    $heureFin,
                    $dateFin,
                );

                $conflits = $this->detecter->execute(
                    $agentId,
                    $dateDebut,
                    $heureDebut,
                    $heureFin,
                    $dateFin,
                    $vacation->id,
                );

                if ($conflits->isNotEmpty()) {
                    $blocker = $conflits->first();
                    throw new VacationConflitException(
                        'Cet agent a déjà une vacation sur ce créneau (même site ou autre site).',
                        $blocker ? (string) $blocker->id : null,
                    );
                }

                AssertVacationDureeLegale::execute(
                    $agentId,
                    $dateDebut,
                    $heureDebut,
                    $heureFin,
                    $dateFin,
                    $vacation->id,
                    ['poste_id' => $posteId],
                );

                AssertPosteEffectifDisponible::execute(
                    $posteId,
                    $dateDebut,
                    $heureDebut,
                    $heureFin,
                    $dateFin,
                    $vacation->id,
                );
            } elseif (array_key_exists('poste_id', $data) || array_key_exists('site_id', $data)) {
                // Même en trou/annulation, le couple poste/site doit rester cohérent.
                AssertAgentAssignable::posteBelongsToSite($posteId, $siteId);
            }

            $vacation->update($data);

            if ($statut !== StatutVacation::Annulee->value
                && $statut !== StatutVacation::Terminee->value
                && $statut !== StatutVacation::ARecouvrir->value) {
                Agent::query()
                    ->whereKey($agentId)
                    ->update(['statut' => StatutAgent::EnActivite]);
            }

            return $vacation->fresh(['agent', 'site', 'poste']);
        });
    }
}
