<?php

namespace App\Policies;

use App\Application\Shared\RondierPerimetreGuard;
use App\Models\Vacation;
use App\Models\User;

class VacationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('vacations.view') || $user->hasRole('super-admin');
    }

    public function view(User $user, Vacation $model): bool
    {
        return $user->can('vacations.view') || $user->hasRole('super-admin');
    }

    public function create(User $user): bool
    {
        return $user->can('vacations.create') || $user->hasRole('super-admin');
    }

    public function update(User $user, Vacation $model): bool
    {
        return $user->can('vacations.update') || $user->hasRole('super-admin');
    }

    /**
     * Planning : toute case. Contrôleur : seulement une case d’aujourd’hui sur son
     * périmètre, pour régler l’absence sur le terrain sans toucher au planning.
     */
    public function recouvrir(User $user, Vacation $model): bool
    {
        if ($this->update($user, $model)) {
            return true;
        }

        $controleur = $user->agent;
        if (! $user->can('vacations.recouvrir') || ! $controleur) {
            return false;
        }

        $aujourdhui = now()->startOfDay();

        return $model->date_debut->lte($aujourdhui)
            && ($model->date_fin === null || $model->date_fin->gte($aujourdhui))
            && in_array((string) $model->site_id, RondierPerimetreGuard::authorizedSiteIds($controleur), true);
    }

    public function delete(User $user, Vacation $model): bool
    {
        return $user->can('vacations.delete') || $user->hasRole('super-admin');
    }
}
