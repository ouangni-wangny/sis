<?php

use App\Domain\Shared\Enums\StatutUser;
use App\Domain\Shared\Enums\TypeUser;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    User::query()->create([
        'nom' => 'Agent',
        'prenom' => 'Mobile',
        'name' => 'Mobile Agent',
        'matricule' => 'MOB-001',
        'pin_hash' => Hash::make('1234'),
        'type' => TypeUser::Mobile,
        'statut' => StatutUser::Actif,
        'email' => null,
        'password' => null,
    ])->assignRole('agent');
});

it('logs in mobile with matricule and pin', function () {
    $this->postJson('/api/v1/auth/mobile-login', [
        'matricule' => 'MOB-001',
        'pin' => '1234',
    ])->assertOk()
        ->assertJsonStructure(['data' => ['token', 'user']]);
});

it('rejects bad mobile pin', function () {
    $this->postJson('/api/v1/auth/mobile-login', [
        'matricule' => 'MOB-001',
        'pin' => '9999',
    ])->assertStatus(422);
});
