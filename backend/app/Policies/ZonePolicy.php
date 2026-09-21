<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Zone;

class ZonePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('zones.view') || $user->can('zones.manage') || $user->hasRole('super-admin');
    }

    public function view(User $user, Zone $model): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('zones.create') || $user->can('zones.manage') || $user->hasRole('super-admin');
    }

    public function update(User $user, Zone $model): bool
    {
        return $user->can('zones.update') || $user->can('zones.manage') || $user->hasRole('super-admin');
    }

    public function delete(User $user, Zone $model): bool
    {
        return $user->can('zones.delete') || $user->can('zones.manage') || $user->hasRole('super-admin');
    }
}
