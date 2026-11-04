<?php

namespace App\Application\Operation;

use App\Domain\Shared\Enums\ResultatControle;
use App\Domain\Shared\Enums\StatutVacation;
use App\Models\Controle;
use App\Models\User;
use App\Models\Vacation;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Une vacation qui a eu lieu sert au pointage et à la paie : seul un super-admin
 * peut encore la changer. Les heures viennent des contrôles « présent », rattachés
 * à la vacation par l’agent et l’heure, donc la modifier ferait perdre ces heures.
 */
final class VacationDejaEffectuee
{
    /** @param  array<string, mixed>  $data */
    public static function refuserModification(Vacation $vacation, array $data, User $user): void
    {
        // Clôturer une vacation en cours reste possible : c’est la suite normale.
        $cloture = $vacation->statut === StatutVacation::EnCours
            && array_keys($data) === ['statut']
            && $data['statut'] === StatutVacation::Terminee->value;

        if (! $cloture) {
            self::refuser($vacation, $user, 'modifiée');
        }
    }

    public static function refuserSuppression(Vacation $vacation, User $user): void
    {
        self::refuser($vacation, $user, 'supprimée');
    }

    private static function refuser(Vacation $vacation, User $user, string $action): void
    {
        if ($user->hasRole('super-admin')) {
            return;
        }

        $motif = match (true) {
            $vacation->statut === StatutVacation::Terminee => 'elle est terminée',
            $vacation->statut === StatutVacation::EnCours => 'elle est en cours',
            self::aUnePresence($vacation) => 'un contrôle a déjà constaté la présence de l’agent',
            default => null,
        };

        if ($motif !== null) {
            throw ValidationException::withMessages([
                'vacation' => "Cette vacation ne peut plus être {$action} : {$motif}, et elle compte pour la paie. Contactez un super-admin.",
            ]);
        }
    }

    public static function aUnePresence(Vacation $vacation): bool
    {
        $debut = Carbon::parse($vacation->date_debut->format('Y-m-d').' '.substr((string) $vacation->heure_debut, 0, 5));
        if ($debut->isFuture()) {
            return false;
        }

        $fin = Carbon::parse(($vacation->date_fin ?? $vacation->date_debut)->format('Y-m-d').' '.substr((string) $vacation->heure_fin, 0, 5));
        if ($fin->lte($debut)) {
            $fin->addDay();
        }

        $tolerance = (int) config('sis.controle.heure_tolerance_minutes', 60);

        return Controle::query()
            ->where('controle_agent_id', $vacation->agent_id)
            ->where('resultat', ResultatControle::Present->value)
            ->whereBetween('effectue_at', [$debut->copy()->subMinutes($tolerance), $fin->copy()->addMinutes($tolerance)])
            ->exists();
    }
}
