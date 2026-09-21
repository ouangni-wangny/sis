<?php

namespace App\Policies;

use App\Models\Site;
use App\Models\User;

class SitePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('sites.view') || $user->hasRole('super-admin');
    }

    public function view(User $user, Site $model): bool
    {
        return $user->can('sites.view') || $user->hasRole('super-admin');
    }

    public function create(User $user): bool
    {
        return $user->can('sites.create') || $user->hasRole('super-admin');
    }

    public function update(User $user, Site $model): bool
    {
        return $user->can('sites.update') || $user->hasRole('super-admin');
    }

    public function delete(User $user, Site $model): bool
    {
        return $user->can('sites.delete') || $user->hasRole('super-admin');
    }
}
