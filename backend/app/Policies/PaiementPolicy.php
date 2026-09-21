<?php

namespace App\Policies;

use App\Models\Paiement;
use App\Models\User;

class PaiementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('paiements.view') || $user->hasRole('super-admin');
    }

    public function view(User $user, Paiement $model): bool
    {
        return $user->can('paiements.view') || $user->hasRole('super-admin');
    }

    public function create(User $user): bool
    {
        return $user->can('paiements.create') || $user->hasRole('super-admin');
    }

    public function update(User $user, Paiement $model): bool
    {
        return $user->can('paiements.update') || $user->hasRole('super-admin');
    }

    public function delete(User $user, Paiement $model): bool
    {
        return $user->can('paiements.delete') || $user->hasRole('super-admin');
    }
}
