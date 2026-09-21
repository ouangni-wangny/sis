<?php

namespace App\Policies;

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

    public function delete(User $user, Vacation $model): bool
    {
        return $user->can('vacations.delete') || $user->hasRole('super-admin');
    }
}
