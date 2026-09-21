<?php

namespace App\Policies;

use App\Models\Facture;
use App\Models\User;

class FacturePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('factures.view') || $user->hasRole('super-admin');
    }

    public function view(User $user, Facture $model): bool
    {
        return $user->can('factures.view') || $user->hasRole('super-admin');
    }

    public function create(User $user): bool
    {
        return $user->can('factures.create') || $user->hasRole('super-admin');
    }

    public function update(User $user, Facture $model): bool
    {
        return $user->can('factures.update') || $user->hasRole('super-admin');
    }

    public function delete(User $user, Facture $model): bool
    {
        return $user->can('factures.delete') || $user->hasRole('super-admin');
    }
}
