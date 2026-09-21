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
        if (! $user) {
            return false;
        }

        return $user->can('contrats.manage') || $user->can('paie.manage');
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

    public static function canViewPaie(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->can('paie.manage') || $user->can('paie.view');
    }

    public static function canManageDocuments(?User $user): bool
    {
        return $user?->can('documents.manage') ?? false;
    }
}
