<?php

namespace App\Application\Identity;

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

final class RevokeDeviceTokenAction
{
    public function execute(User $user, ?string $tokenId = null): void
    {
        if ($tokenId) {
            $user->tokens()->where('id', $tokenId)->delete();

            return;
        }

        /** @var PersonalAccessToken|null $current */
        $current = $user->currentAccessToken();
        $current?->delete();
    }
}
