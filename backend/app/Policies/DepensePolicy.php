<?php

namespace App\Policies;

use App\Models\Depense;
use App\Models\User;

class DepensePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('depenses.view')
            || $user->can('depenses.manage')
            || $user->can('tresorerie.view')
            || $user->can('tresorerie.manage')
            || $user->hasRole('super-admin');
    }

    public function view(User $user, Depense $model): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('depenses.manage')
            || $user->can('tresorerie.manage')
            || $user->hasRole('super-admin');
    }

    public function delete(User $user, Depense $model): bool
    {
        return $this->create($user);
    }
}
