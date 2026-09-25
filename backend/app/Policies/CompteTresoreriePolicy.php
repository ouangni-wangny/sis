<?php

namespace App\Policies;

use App\Models\CompteTresorerie;
use App\Models\User;

class CompteTresoreriePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('tresorerie.view')
            || $user->can('tresorerie.manage')
            || $user->can('grades.manage')
            || $user->can('paie.manage')
            || $user->can('paie.view')
            || $user->can('paiements.view')
            || $user->can('paiements.create')
            || $user->hasRole('super-admin');
    }

    public function view(User $user, CompteTresorerie $model): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('tresorerie.manage')
            || $user->can('grades.manage')
            || $user->hasRole('super-admin');
    }

    public function update(User $user, CompteTresorerie $model): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, CompteTresorerie $model): bool
    {
        return $this->create($user);
    }
}
