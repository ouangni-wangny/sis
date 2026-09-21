<?php

namespace App\Policies;

use App\Models\Abonnement;
use App\Models\User;

class AbonnementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('abonnements.view') || $user->hasRole('super-admin');
    }

    public function view(User $user, Abonnement $model): bool
    {
        return $user->can('abonnements.view') || $user->hasRole('super-admin');
    }

    public function create(User $user): bool
    {
        return $user->can('abonnements.create') || $user->hasRole('super-admin');
    }

    public function update(User $user, Abonnement $model): bool
    {
        return $user->can('abonnements.update') || $user->hasRole('super-admin');
    }

    public function delete(User $user, Abonnement $model): bool
    {
        return $user->can('abonnements.delete') || $user->hasRole('super-admin');
    }
}
