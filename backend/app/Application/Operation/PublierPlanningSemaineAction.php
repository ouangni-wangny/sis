<?php

namespace App\Application\Operation;

use App\Domain\Shared\Enums\StatutPlanningSemaine;
use App\Domain\Shared\Enums\StatutRonde;
use App\Domain\Shared\Enums\StatutVacation;
use App\Domain\Shared\Support\ShiftInterval;
use App\Models\PlanningSemaine;
use App\Models\Ronde;
use App\Models\Vacation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PublierPlanningSemaineAction
{
    public function __construct(
        private readonly CreateVacationAction $createVacation,
    ) {}

    public function execute(PlanningSemaine $planning): PlanningSemaine
    {
        return DB::transaction(function () use ($planning) {
            $planning = $planning->fresh(['creneaux', 'poste.site']) ?? $planning;

            if ($planning->creneaux->isEmpty()) {
                throw ValidationException::withMessages([
                    'creneaux' => 'Ajoutez au moins un créneau avant de publier.',
                ]);
            }

            $poste = $planning->poste;
            $siteId = $poste->site_id;
            $lundi = $planning->lundi()->toDateString();
            $dimanche = $planning->dimanche()->toDateString();
            $aujourdHui = now('Africa/Abidjan')->toDateString();

            // Remplacer le prévu sur la semaine (sauf passé / remplacements / HS / remplacée).
            $toRemove = Vacation::query()
                ->where('poste_id', $poste->id)
                ->where('est_remplacement', false)
                ->where('heures_supplementaires', false)
                ->where(fn ($q) => $q
                    ->where('statut', StatutVacation::Planifiee->value)
                    ->orWhere(fn ($t) => $t
                        ->where('statut', StatutVacation::ARecouvrir->value)
                        ->whereNotNull('absence_id')))
                ->whereDate('date_debut', '>=', max($lundi, $aujourdHui))
                ->whereDate('date_debut', '<=', $dimanche)
                ->lockForUpdate()
                ->get();

            if ($toRemove->isNotEmpty()) {
                $ids = $toRemove->pluck('id')->all();
                $hasOpenRonde = Ronde::query()
                    ->whereIn('vacation_id', $ids)
                    ->whereIn('statut', [StatutRonde::Planifiee, StatutRonde::EnCours])
                    ->exists();

                if ($hasOpenRonde) {
                    throw ValidationException::withMessages([
                        'planning' => 'Impossible de publier : une ronde liée est encore planifiée ou en cours.',
                    ]);
                }

                Vacation::query()->whereIn('id', $ids)->delete();
            }

            foreach ($planning->creneaux as $creneau) {
                $dateDebut = $creneau->datePourSemaine($planning);
                if ($dateDebut < $aujourdHui) {
                    continue;
                }

                $dateFin = $creneau->dateFinPourSemaine($planning);
                $row = [
                    'agent_id' => $creneau->agent_id,
                    'site_id' => $siteId,
                    'poste_id' => $poste->id,
                    'date_debut' => $dateDebut,
                    'date_fin' => $dateFin,
                    'heure_debut' => $creneau->heureDebutHm(),
                    'heure_fin' => $creneau->heureFinHm(),
                    'reconduite' => false,
                    'est_remplacement' => false,
                    'heures_supplementaires' => false,
                    'statuts_autorises' => ProlongerPlanningPostesAction::STATUTS_RECONDUCTIBLES,
                ];

                if ($this->creneauDejaTraite($row)) {
                    continue;
                }

                $absence = TrouAbsence::absenceDuJour($row['agent_id'], $dateDebut);
                if ($absence) {
                    TrouAbsence::creer(
                        array_diff_key($row, [
                            'date_debut' => 1,
                            'date_fin' => 1,
                            'statuts_autorises' => 1,
                        ]),
                        $dateDebut,
                        $absence,
                    );

                    continue;
                }

                $this->createVacation->execute($row, wrapInTransaction: false);
            }

            $planning->update(['statut' => StatutPlanningSemaine::Publiee]);

            $poste->update([
                'planning_date_debut' => $lundi,
                'planning_date_fin' => $dimanche,
            ]);

            return $planning->fresh(['creneaux.agent', 'poste.site']);
        });
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function creneauDejaTraite(array $row): bool
    {
        return Vacation::query()
            ->where('agent_id', $row['agent_id'])
            ->where('poste_id', $row['poste_id'] ?? null)
            ->whereDate('date_debut', $row['date_debut'])
            ->where(fn ($q) => $q->where('statut', '!=', StatutVacation::Annulee->value)->orWhere('remplacee', true))
            ->get()
            ->contains(fn (Vacation $v) => ShiftInterval::hm((string) $v->heure_debut) === ShiftInterval::hm((string) $row['heure_debut']));
    }
}
