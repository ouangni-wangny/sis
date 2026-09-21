<?php

namespace Database\Factories;

use App\Domain\Shared\Enums\StatutUser;
use App\Domain\Shared\Enums\TypeUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        $nom = fake()->lastName();
        $prenom = fake()->firstName();

        return [
            'nom' => $nom,
            'prenom' => $prenom,
            'name' => "{$prenom} {$nom}",
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'pin_hash' => null,
            'matricule' => null,
            'type' => TypeUser::Backoffice,
            'statut' => StatutUser::Actif,
            'remember_token' => Str::random(10),
        ];
    }

    public function mobile(): static
    {
        return $this->state(fn () => [
            'email' => null,
            'password' => null,
            'type' => TypeUser::Mobile,
            'matricule' => 'AG-'.fake()->unique()->numerify('####'),
            'pin_hash' => Hash::make('1234'),
        ]);
    }

    public function unverified(): static
    {
        return $this->state(fn () => [
            'email_verified_at' => null,
        ]);
    }
}
