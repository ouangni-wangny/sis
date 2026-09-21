<?php

use App\Domain\Shared\Enums\StatutUser;
use App\Domain\Shared\Enums\TypeAgent;
use App\Domain\Shared\Enums\TypeUser;
use App\Models\Agent;
use App\Models\Contrat;
use App\Models\Grade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Crée un agent avec un compte utilisateur mobile (PIN) et un rôle donné.
 * Partagé entre les tests Feature qui simulent une session mobile
 * (Sanctum::actingAs($agent->user, ['mobile'])).
 */
function makeMobileAgent(string $matricule, string $role): Agent
{
    $grade = Grade::query()->create([
        'libelle' => "Grade {$matricule}",
        'type_agent' => TypeAgent::Agent,
    ]);

    $user = User::query()->create([
        'nom' => 'Test',
        'prenom' => $matricule,
        'name' => "Test {$matricule}",
        'matricule' => $matricule,
        'pin_hash' => Hash::make('1234'),
        'type' => TypeUser::Mobile,
        'statut' => StatutUser::Actif,
        'email' => null,
        'password' => null,
    ]);
    $user->assignRole($role);

    return Agent::query()->create([
        'user_id' => $user->id,
        'grade_id' => $grade->id,
        'type' => TypeAgent::Agent,
        'nom' => 'Test',
        'prenom' => $matricule,
        'matricule' => $matricule,
        'statut' => 'disponible',
    ]);
}

/** Attache un contrat CDI actif à un agent (tests opération / vacations). */
function ensureContratActif(Agent $agent, array $overrides = []): Contrat
{
    $existing = Contrat::query()
        ->where('agent_id', $agent->id)
        ->where('statut', 'actif')
        ->first();

    if ($existing) {
        return $existing;
    }

    return Contrat::query()->create(array_merge([
        'agent_id' => $agent->id,
        'type' => 'cdi',
        'date_debut' => '2026-01-01',
        'statut' => 'actif',
    ], $overrides));
}
