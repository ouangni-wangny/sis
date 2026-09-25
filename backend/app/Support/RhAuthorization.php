<?php

namespace App\Support;

use App\Models\User;

final class RhAuthorization
{
    public static function canManageContrats(?User $user): bool
    {
        return $user?->can('contrats.manage') ?? false;
    }

    public static function canViewContrats(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->can('contrats.manage')
            || $user->can('contrats.view')
            || $user->can('contrats.alerts');
    }

    public static function canSeeSalaire(?User $user): bool
    {
        // Salaires réservés à la RH (gestion contrats), pas au règlement comptable.
        return $user?->can('contrats.manage') ?? false;
    }

    public static function canManageAbsences(?User $user): bool
    {
        return $user?->can('absences.manage') ?? false;
    }

    public static function canViewAbsences(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->can('absences.manage') || $user->can('absences.view');
    }

    public static function canManagePaie(?User $user): bool
    {
        return $user?->can('paie.manage') ?? false;
    }

    /** Règlement (marquer payé) — typiquement le comptable. */
    public static function canPayerPaie(?User $user): bool
    {
        return $user?->can('paie.payer') ?? false;
    }

    public static function canViewPaie(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->can('paie.manage')
            || $user->can('paie.view')
            || $user->can('paie.payer');
    }

    public static function canManageDocuments(?User $user): bool
    {
        return $user?->can('documents.manage') ?? false;
    }
}
