<?php

namespace App\Application\Operation;

use App\Domain\Shared\Enums\StatutVacation;
use App\Models\Absence;
use App\Models\Vacation;

/**
 * Passe en « à recouvrir » les vacations d’un agent qui chevauchent
 * une période d’absence (logique partagée RH / contrôle terrain).
 */
final class MarquerVacationsARecouvrir
{
    public static function execute(
        Absence $absence,
        string $agentId,
        string $debut,
        string $fin,
    ): int {
        $vacations = Vacation::query()
            ->where('agent_id', $agentId)
            ->whereIn('statut', [
                StatutVacation::Planifiee->value,
                StatutVacation::EnCours->value,
            ])
            ->whereDate('date_debut', '<=', $fin)
            ->where(function ($q) use ($debut) {
                $q->whereNull('date_fin')
                    ->orWhereDate('date_fin', '>=', $debut);
            })
            ->get()
            ->filter(fn (Vacation $v) => self::vacationPlanningDayOverlapsAbsence($v, $debut, $fin));

        foreach ($vacations as $vacation) {
            $vacation->update([
                'statut' => StatutVacation::ARecouvrir->value,
                'absence_id' => $absence->id,
            ]);
        }

        return $vacations->count();
    }

    public static function vacationPlanningDayOverlapsAbsence(
        Vacation $v,
        string $debut,
        string $fin,
    ): bool {
        $priseDePoste = $v->date_debut->format('Y-m-d');
        $heureDebut = substr((string) $v->heure_debut, 0, 5);
        $heureFin = substr((string) $v->heure_fin, 0, 5);
        $isOvernight = $heureFin < $heureDebut;

        if ($isOvernight) {
            return $priseDePoste >= $debut && $priseDePoste <= $fin;
        }

        $finVacation = $v->date_fin?->format('Y-m-d') ?? $priseDePoste;

        return $priseDePoste <= $fin && $finVacation >= $debut;
    }
}
