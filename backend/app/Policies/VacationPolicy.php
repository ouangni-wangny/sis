<?php

namespace App\Policies;

use App\Application\Shared\RondierPerimetreGuard;
use App\Models\Vacation;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class VacationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('vacations.view') || $user->hasRole('super-admin');
    }

    public function view(User $user, Vacation $model): bool
    {
        return ($user->can('vacations.view') || $user->hasRole('super-admin'))
            && Vacation::query()->visiblesPar($user)->whereKey($model->id)->exists();
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
    public function recouvrir(User $user, Vacation $model): bool|Response
    {
        if ($this->update($user, $model)) {
            return true;
        }

        if (! $user->can('vacations.recouvrir')) {
            return false;
        }

        $controleur = $user->agent;
        if (! $controleur) {
            return Response::deny('Votre compte n’est lié à aucune fiche agent. Contactez l’administrateur.');
        }

        $aujourdhui = now()->startOfDay();
        if ($model->date_debut->gt($aujourdhui) || ($model->date_fin !== null && $model->date_fin->lt($aujourdhui))) {
            return Response::deny('Sur le terrain, seules les cases d’aujourd’hui peuvent être recouvertes. Pour un autre jour, contactez le planning.');
        }

        if (! in_array((string) $model->site_id, RondierPerimetreGuard::authorizedSiteIds($controleur), true)) {
            return Response::deny('Ce site n’est pas dans votre périmètre. Contactez le planning.');
        }

        return true;
    }

    public function delete(User $user, Vacation $model): bool
    {
        return $user->can('vacations.delete') || $user->hasRole('super-admin');
    }
}
