<?php

namespace App\Application\Identity;

use App\Domain\Shared\Enums\StatutUser;
use App\Domain\Shared\Enums\TypeUser;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class LoginWebAction
{
    public function execute(string $email, string $password, ?string $deviceName = 'web'): array
    {
        $user = User::query()
            ->where('email', $email)
            ->where('type', TypeUser::Backoffice)
            ->first();

        if (! $user || ! $user->password || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Identifiants invalides.'],
            ]);
        }

        if ($user->statut !== StatutUser::Actif) {
            throw ValidationException::withMessages([
                'email' => ['Compte inactif.'],
            ]);
        }

        $user->forceFill(['last_login_at' => now()])->save();
        $token = $user->createToken($deviceName, ['backoffice'])->plainTextToken;

        return compact('user', 'token');
    }
}
