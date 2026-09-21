<?php

namespace App\Application\Operation;

use App\Domain\Shared\Support\ShiftInterval;
use Illuminate\Validation\ValidationException;

/**
 * Normalise et valide les dates/heures d’une vacation
 * (nuit / cycle 24h → date_fin +1 j, cohérence date_fin ≥ date_debut).
 */
final class NormalizeVacationSchedule
{
    /**
     * @return array{date_debut: string, date_fin: ?string, heure_debut: string, heure_fin: string}
     */
    public static function apply(
        string $dateDebut,
        string $heureDebut,
        string $heureFin,
        ?string $dateFin = null,
    ): array {
        $heureDebut = ShiftInterval::hm($heureDebut);
        $heureFin = ShiftInterval::hm($heureFin);
        $dateDebut = \Carbon\Carbon::parse($dateDebut)->toDateString();
        $dateFin = $dateFin !== null && $dateFin !== ''
            ? \Carbon\Carbon::parse($dateFin)->toDateString()
            : null;

        $dateFin = ShiftInterval::dateFinForShift(
            $dateDebut,
            $heureDebut,
            $heureFin,
            $dateFin,
        );

        if ($dateFin !== null && $dateFin < $dateDebut) {
            throw ValidationException::withMessages([
                'date_fin' => 'La date de fin doit être postérieure ou égale à la date de début.',
            ]);
        }

        return [
            'date_debut' => $dateDebut,
            'date_fin' => $dateFin,
            'heure_debut' => $heureDebut,
            'heure_fin' => $heureFin,
        ];
    }
}
