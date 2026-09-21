<?php

namespace App\Policies;

use App\Models\Offre;
use App\Models\User;

class OffrePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('offres.view') || $user->hasRole('super-admin');
    }

    public function view(User $user, Offre $model): bool
    {
        return $user->can('offres.view') || $user->hasRole('super-admin');
    }

    public function create(User $user): bool
    {
        return $user->can('offres.create') || $user->hasRole('super-admin');
    }

    public function update(User $user, Offre $model): bool
    {
        return $user->can('offres.update') || $user->hasRole('super-admin');
    }

    public function delete(User $user, Offre $model): bool
    {
        return $user->can('offres.delete') || $user->hasRole('super-admin');
    }
}
