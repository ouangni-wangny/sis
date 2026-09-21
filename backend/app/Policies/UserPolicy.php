<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('users.view') || $user->hasRole('super-admin');
    }

    public function view(User $user, User $model): bool
    {
        return $user->can('users.view') || $user->hasRole('super-admin');
    }

    public function create(User $user): bool
    {
        return $user->can('users.create') || $user->hasRole('super-admin');
    }

    public function update(User $user, User $model): bool
    {
        return $user->can('users.update') || $user->hasRole('super-admin');
    }

    public function delete(User $user, User $model): bool
    {
        return $user->can('users.delete') || $user->hasRole('super-admin');
    }
}
