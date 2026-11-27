<?php

namespace App\Application\Operation;

use App\Domain\Shared\Support\ShiftInterval;
use App\Models\Agent;
use Illuminate\Validation\ValidationException;

/**
 * Contrainte horaire d'une vacation : durée maximale d'un créneau
 * (config('sis.vacation.duree_max_heures')).
 *
 * Il n'y a plus de repos minimum entre deux vacations : un agent peut
 * enchaîner (ex. jour sur un site, nuit sur un autre). Le chevauchement
 * reste interdit (DetecterConflitsVacationAction).
 */
final class AssertVacationDureeLegale
{
    /**
     * @param  array<string, mixed>  $options  conservé pour compatibilité (plus de règle de repos)
     */
    public static function execute(
        string $agentId,
        string $dateDebut,
        string $heureDebut,
        string $heureFin,
        ?string $dateFin = null,
        ?string $excludeId = null,
        array $options = [],
    ): void {
        $agentLabel = self::agentLabel($agentId);

        $dureeMax = (float) config('sis.vacation.duree_max_heures');
        $dureeHeures = ShiftInterval::durationHours($heureDebut, $heureFin);
        $isCycle24h = ShiftInterval::isCycle24h($heureDebut, $heureFin);

        // Cycle 24h (relève→relève) : exemption explicite du plafond créneau
        // (un agent seul sur surveillance continue). Les moitiés restent ≤ max.
        if (! $isCycle24h && $dureeHeures > $dureeMax) {
            throw ValidationException::withMessages([
                'heure_fin' => sprintf(
                    '%s — durée de vacation trop longue : %.1fh (maximum %.1fh).',
                    $agentLabel,
                    $dureeHeures,
                    $dureeMax,
                ),
            ]);
        }

        // Plus de repos minimum imposé entre deux vacations (décision client) :
        // seul le chevauchement reste interdit (DetecterConflitsVacationAction).
    }

    private static function agentLabel(string $agentId): string
    {
        $agent = Agent::query()->find($agentId);
        if (! $agent) {
            return 'Agent';
        }

        return trim(sprintf(
            '%s %s (%s)',
            $agent->prenom,
            $agent->nom,
            $agent->matricule,
        ));
    }
}
