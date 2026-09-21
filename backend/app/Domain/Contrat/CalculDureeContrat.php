<?php

namespace App\Domain\Contrat;

use Carbon\Carbon;

final class CalculDureeContrat
{
    /** Durée calendaire en mois entre date_debut et date_fin (inclusif sur le mois). */
    public static function fromDates(?string $dateDebut, ?string $dateFin): ?int
    {
        if (blank($dateDebut) || blank($dateFin)) {
            return null;
        }

        $start = Carbon::parse($dateDebut)->startOfDay();
        $end = Carbon::parse($dateFin)->startOfDay();

        if ($end->lt($start)) {
            return null;
        }

        return ($end->year - $start->year) * 12 + ($end->month - $start->month);
    }
}
