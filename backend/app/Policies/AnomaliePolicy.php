<?php

namespace App\Policies;

use App\Models\Anomalie;
use App\Models\User;

class AnomaliePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('anomalies.view') || $user->hasRole('super-admin');
    }

    public function view(User $user, Anomalie $model): bool
    {
        return $user->can('anomalies.view') || $user->hasRole('super-admin');
    }

    public function create(User $user): bool
    {
        return $user->can('anomalies.create') || $user->hasRole('super-admin');
    }

    public function update(User $user, Anomalie $model): bool
    {
        return $user->can('anomalies.update') || $user->hasRole('super-admin');
    }

    public function delete(User $user, Anomalie $model): bool
    {
        return $user->can('anomalies.delete') || $user->hasRole('super-admin');
    }
}
