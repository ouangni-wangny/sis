<?php

namespace App\Application\Operation;

use App\Domain\Shared\Enums\StatutRonde;
use App\Domain\Shared\Enums\StatutVacation;
use App\Domain\Shared\Support\ShiftInterval;
use App\Models\Poste;
use App\Models\Ronde;
use App\Models\Vacation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateBulkVacationsAction
{
    public function __construct(
        private CreateVacationAction $create,
    ) {}

    /**
     * @param  array{
     *   vacations: list<array<string, mixed>>,
     *   annuler_conflits?: bool,
     *   replace?: array{
     *     poste_id: string,
     *     date_debut: string,
     *     date_fin: string,
     *     agent_ids?: list<string>,
     *     conserver_agent_ids?: list<string>,
     *     scope?: 'agents'|'poste'
     *   }
     * }  $data
     * @return array{created: int, removed: int, vacations: list<Vacation>}
     */
    public function execute(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $annulerConflits = (bool) ($data['annuler_conflits'] ?? false);
            $removed = 0;

            if (! empty($data['replace'])) {
                $removed = $this->replaceExisting($data['replace']);

                if (($data['replace']['scope'] ?? 'agents') === 'poste') {
                    Poste::query()->whereKey($data['replace']['poste_id'])->update([
                        'planning_date_debut' => $data['replace']['date_debut'],
                        'planning_date_fin' => $data['replace']['date_fin'],
                    ]);
                }
            }

            $created = [];
            foreach ($data['vacations'] as $row) {
                if (! empty($data['replace'])) {
                    // Replanifier : ce qui a eu lieu ou a été remplacé garde sa place.
                    if ($this->creneauDejaTraite($row)) {
                        continue;
                    }
                    // Agent absent ce jour-là : le planning passe, la case est à recouvrir.
                    $absence = TrouAbsence::absenceDuJour($row['agent_id'], $row['date_debut']);
                    if ($absence) {
                        $created[] = TrouAbsence::creer(array_diff_key($row, ['date_debut' => 1, 'date_fin' => 1]), $row['date_debut'], $absence);

                        continue;
                    }
                    // Un titulaire en congé aujourd’hui peut reprendre à une date planifiée.
                    $row['statuts_autorises'] = ProlongerPlanningPostesAction::STATUTS_RECONDUCTIBLES;
                }

                $created[] = $this->create->execute(
                    [
                        ...$row,
                        'annuler_conflits' => $annulerConflits,
                    ],
                    wrapInTransaction: false,
                );
            }

            return [
                'created' => count($created),
                'removed' => $removed,
                'vacations' => $created,
            ];
        });
    }

    /**
     * Le créneau de cet agent existe encore après le remplacement du prévu, ou il
     * l’a cédé à un remplaçant toujours en place.
     *
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

    /**
     * @param  array{
     *   poste_id: string,
     *   date_debut: string,
     *   date_fin: string,
     *   agent_ids?: list<string>,
     *   conserver_agent_ids?: list<string>,
     *   scope?: 'agents'|'poste'
     * }  $replace
     */
    private function replaceExisting(array $replace): int
    {
        $scope = $replace['scope'] ?? 'agents';

        $query = Vacation::query()
            ->where('poste_id', $replace['poste_id'])
            // Seul le prévu est remplacé : ni le passé (pointage, paie), ni ce qui a démarré ou été pointé,
            // ni les remplacements saisis, ni une case marquée à la main.
            ->where('est_remplacement', false)
            ->where(fn ($q) => $q->where('statut', StatutVacation::Planifiee->value)
                ->orWhere(fn ($t) => $t->where('statut', StatutVacation::ARecouvrir->value)->whereNotNull('absence_id')))
            ->whereDate('date_debut', '>=', max($replace['date_debut'], now()->toDateString()))
            // Au-delà de la période, seules les semaines reconduites suivaient l’ancien planning.
            ->where(fn ($q) => $q->whereDate('date_debut', '<=', $replace['date_fin'])->orWhere('reconduite', true));

        // Planifier le poste : on vide TOUT le poste sur la période
        // (évite un 3ᵉ agent orphelin hors agent_ids).
        if ($scope !== 'poste') {
            $agentIds = $replace['agent_ids'] ?? [];
            if ($agentIds === []) {
                return 0;
            }
            $query->whereIn('agent_id', $agentIds);
        }

        // Agent du poste hors de la grille (ex. relève du jour de repos) : ses créneaux restent.
        if (! empty($replace['conserver_agent_ids'])) {
            $query->whereNotIn('agent_id', $replace['conserver_agent_ids']);
        }

        $toRemove = $query->lockForUpdate()->get()
            ->reject(fn (Vacation $v) => VacationDejaEffectuee::aUnePresence($v));

        if ($toRemove->isEmpty()) {
            return 0;
        }

        $ids = $toRemove->pluck('id')->all();
        $hasOpenRonde = Ronde::query()
            ->whereIn('vacation_id', $ids)
            ->whereIn('statut', [StatutRonde::Planifiee, StatutRonde::EnCours])
            ->exists();

        if ($hasOpenRonde) {
            throw ValidationException::withMessages([
                'replace' => 'Impossible de remplacer : une ronde liée est encore planifiée ou en cours.',
            ]);
        }

        Vacation::query()->whereIn('id', $ids)->delete();

        return count($ids);
    }
}
