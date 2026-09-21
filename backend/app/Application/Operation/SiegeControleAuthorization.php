<?php

namespace App\Application\Operation;

use App\Models\Site;
use App\Models\User;
use App\Support\FeatureFlagRegistry;

/**
 * Opération (back-office) peut contrôler les agents sur un site marqué interne
 * (siège) sans être un contrôleur terrain ni avoir de périmètre zone.
 */
final class SiegeControleAuthorization
{
    public static function canOperate(?User $user, Site $site): bool
    {
        if (! $user || ! $site->interne) {
            return false;
        }

        if (! FeatureFlagRegistry::enabled('module.controles_siege')) {
            return false;
        }

        return $user->hasRole('operation')
            || $user->hasRole('super-admin')
            || $user->hasRole('developpeur');
    }
}
