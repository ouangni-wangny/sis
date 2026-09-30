<?php

namespace App\Policies;

use App\Models\CompteTresorerie;
use App\Models\User;

class CompteTresoreriePolicy
{
    /**
     * Accès soldes / journal / stats (module Trésorerie).
     * RH et Commercial n'ont pas ces droits (ADR-0001).
     */
    public function viewAny(User $user): bool
    {
        return $user->can('tresorerie.view')
            || $user->can('tresorerie.manage')
            || $user->hasRole('super-admin');
    }

    public function view(User $user, CompteTresorerie $model): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Liste légère id/libellé/type pour formulaires paie & encaissements,
     * sans soldes ni solde d'ouverture.
     */
    public function selectOptions(User $user): bool
    {
        return $this->viewAny($user)
            || $user->can('paie.manage')
            || $user->can('paie.view')
            || $user->can('paie.payer')
            || $user->can('paiements.view')
            || $user->can('paiements.create')
            || $user->can('depenses.view')
            || $user->can('depenses.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('tresorerie.manage')
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
