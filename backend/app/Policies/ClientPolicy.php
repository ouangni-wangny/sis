<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;

class ClientPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('clients.view') || $user->hasRole('super-admin');
    }

    public function view(User $user, Client $model): bool
    {
        return $user->can('clients.view') || $user->hasRole('super-admin');
    }

    public function create(User $user): bool
    {
        return $user->can('clients.create') || $user->hasRole('super-admin');
    }

    public function update(User $user, Client $model): bool
    {
        return $user->can('clients.update') || $user->hasRole('super-admin');
    }

    public function delete(User $user, Client $model): bool
    {
        return $user->can('clients.delete') || $user->hasRole('super-admin');
    }
}
