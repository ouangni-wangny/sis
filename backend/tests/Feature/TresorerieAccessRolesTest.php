<?php

use App\Models\CompteTresorerie;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->compte = CompteTresorerie::query()->create([
        'libelle' => 'Banque test',
        'type' => 'banque',
        'solde_ouverture' => 2_500_000,
        'actif' => true,
    ]);

    $this->rh = User::factory()->create([
        'email' => 'rh-treso@sis.ci',
        'password' => Hash::make('password'),
    ]);
    $this->rh->assignRole('rh');

    $this->commercial = User::factory()->create([
        'email' => 'com-treso@sis.ci',
        'password' => Hash::make('password'),
    ]);
    $this->commercial->assignRole('commercial');

    $this->comptable = User::factory()->create([
        'email' => 'comptable-treso@sis.ci',
        'password' => Hash::make('password'),
    ]);
    $this->comptable->assignRole('comptable');
});

it('refuse à la RH et au commercial l’accès aux soldes et stats', function (string $role) {
    $user = $role === 'rh' ? $this->rh : $this->commercial;

    $this->actingAs($user)->getJson('/api/v1/comptes-tresorerie')
        ->assertForbidden();

    $this->actingAs($user)->getJson('/api/v1/tresorerie/stats')
        ->assertForbidden();

    $this->actingAs($user)->getJson('/api/v1/tresorerie/mouvements')
        ->assertForbidden();

    $this->actingAs($user)->getJson('/api/v1/categories-depense')
        ->assertForbidden();
})->with(['rh', 'commercial']);

it('autorise le commercial à lister les comptes options sans soldes', function () {
    $response = $this->actingAs($this->commercial)
        ->getJson('/api/v1/comptes-tresorerie-options?actif_only=1')
        ->assertOk();

    $row = collect($response->json('data'))->firstWhere('id', $this->compte->id);
    expect($row)->not->toBeNull()
        ->and($row)->toHaveKeys(['id', 'libelle', 'type', 'actif'])
        ->and($row)->not->toHaveKey('solde')
        ->and($row)->not->toHaveKey('solde_ouverture');
});

it('autorise le comptable à voir soldes et stats', function () {
    $response = $this->actingAs($this->comptable)
        ->getJson('/api/v1/comptes-tresorerie')
        ->assertOk();

    expect((float) $response->json('data.0.solde_ouverture'))->toBe(2_500_000.0);

    $this->actingAs($this->comptable)
        ->getJson('/api/v1/tresorerie/stats')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'chiffre_affaires_mois' => ['total_ttc', 'total_ht', 'count'],
                'solde_consolide',
            ],
        ]);
});
