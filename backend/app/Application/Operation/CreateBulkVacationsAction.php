<?php

namespace App\Application\Operation;

use App\Domain\Shared\Enums\StatutRonde;
use App\Domain\Shared\Enums\StatutVacation;
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
            }

            $created = [];
            foreach ($data['vacations'] as $row) {
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
     * @param  array{
     *   poste_id: string,
     *   date_debut: string,
     *   date_fin: string,
     *   agent_ids?: list<string>,
     *   scope?: 'agents'|'poste'
     * }  $replace
     */
    private function replaceExisting(array $replace): int
    {
        $scope = $replace['scope'] ?? 'agents';

        $query = Vacation::query()
            ->where('poste_id', $replace['poste_id'])
            ->where('statut', '!=', StatutVacation::Annulee->value)
            ->whereDate('date_debut', '>=', $replace['date_debut'])
            ->whereDate('date_debut', '<=', $replace['date_fin']);

        // Planifier le poste : on vide TOUT le poste sur la période
        // (évite un 3ᵉ agent orphelin hors agent_ids).
        if ($scope !== 'poste') {
            $agentIds = $replace['agent_ids'] ?? [];
            if ($agentIds === []) {
                return 0;
            }
            $query->whereIn('agent_id', $agentIds);
        }

        $toRemove = $query->lockForUpdate()->get();

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
