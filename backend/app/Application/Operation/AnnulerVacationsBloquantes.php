<?php

namespace App\Application\Operation;

use App\Domain\Shared\Enums\StatutRonde;
use App\Domain\Shared\Enums\StatutVacation;
use App\Models\Ronde;
use App\Models\Vacation;
use Illuminate\Support\Collection;

/**
 * Annule (statut annulee) des vacations qui bloquent une planification.
 * Ignore celles liées à une ronde encore ouverte.
 */
final class AnnulerVacationsBloquantes
{
    /**
     * @param  Collection<int, Vacation>|list<string>  $vacationsOrIds
     * @return array{cancelled: int, skipped_ronde: int}
     */
    public static function execute(Collection|array $vacationsOrIds): array
    {
        $ids = $vacationsOrIds instanceof Collection
            ? $vacationsOrIds->map(fn ($v) => is_string($v) ? $v : (string) $v->id)->all()
            : array_map('strval', $vacationsOrIds);

        $ids = array_values(array_unique(array_filter($ids)));
        if ($ids === []) {
            return ['cancelled' => 0, 'skipped_ronde' => 0];
        }

        $vacations = Vacation::query()
            ->whereIn('id', $ids)
            ->whereNotIn('statut', [StatutVacation::Annulee->value])
            ->get();

        $cancelled = 0;
        $skipped = 0;

        foreach ($vacations as $vacation) {
            $hasOpenRonde = Ronde::query()
                ->where('vacation_id', $vacation->id)
                ->whereIn('statut', [StatutRonde::Planifiee, StatutRonde::EnCours])
                ->exists();

            if ($hasOpenRonde) {
                $skipped++;
                continue;
            }

            $vacation->update(['statut' => StatutVacation::Annulee->value]);
            $cancelled++;
        }

        return ['cancelled' => $cancelled, 'skipped_ronde' => $skipped];
    }
}
