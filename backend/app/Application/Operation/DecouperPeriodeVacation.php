<?php

namespace App\Application\Operation;

use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Une affectation saisie sur une période (du … au …) devient une vacation
 * par jour de prise de poste — comme « Planifier le poste ».
 *
 * Saisie : date_fin = dernier jour de prise de poste (une nuit du 31 finit
 * le 1er au matin ; l’API pousse date_fin +1 à la normalisation).
 *
 * Une vacation par jour = affichage correct jour après jour, remplacement
 * d’un seul jour possible, et l’agent reste libre hors de ses horaires.
 */
final class DecouperPeriodeVacation
{
    public const MAX_JOURS = 366;

    /**
     * @param  array<string, mixed>  $data
     * @return list<array<string, mixed>>
     */
    public static function rows(array $data): array
    {
        $dateFin = $data['date_fin'] ?? null;
        if ($dateFin === null || $dateFin === '') {
            return [$data];
        }

        $debut = Carbon::parse($data['date_debut'])->startOfDay();
        $fin = Carbon::parse($dateFin)->startOfDay();
        if ($fin->lte($debut)) {
            return [$data];
        }

        $jours = (int) $debut->diffInDays($fin) + 1;
        if ($jours > self::MAX_JOURS) {
            throw ValidationException::withMessages([
                'date_fin' => sprintf('Période trop longue (%d jours, maximum %d).', $jours, self::MAX_JOURS),
            ]);
        }

        $rows = [];
        for ($d = $debut->copy(); $d->lte($fin); $d->addDay()) {
            $rows[] = [
                ...$data,
                'date_debut' => $d->toDateString(),
                'date_fin' => $d->toDateString(),
            ];
        }

        return $rows;
    }
}
