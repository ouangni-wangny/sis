<?php

namespace App\Application\Identity;

use App\Domain\Shared\Enums\StatutUser;
use App\Domain\Shared\Enums\TypeUser;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class LoginMobileAction
{
    public function execute(string $matricule, string $pin, ?string $deviceName = 'mobile'): array
    {
        $user = User::query()
            ->where('matricule', $matricule)
            ->where('type', TypeUser::Mobile)
            ->first();

        if (! $user || ! $user->pin_hash || ! Hash::check($pin, $user->pin_hash)) {
            throw ValidationException::withMessages([
                'matricule' => ['Matricule ou PIN invalide.'],
            ]);
        }

        if ($user->statut !== StatutUser::Actif) {
            throw ValidationException::withMessages([
                'matricule' => ['Compte inactif.'],
            ]);
        }

        $user->forceFill(['last_login_at' => now()])->save();
        $token = $user->createToken($deviceName, ['mobile'])->plainTextToken;

        return [
            'user' => $user->load(['agent.grade', 'agent.perimetres.zone', 'agent.perimetres.site', 'roles', 'permissions']),
            'token' => $token,
        ];
    }
}
