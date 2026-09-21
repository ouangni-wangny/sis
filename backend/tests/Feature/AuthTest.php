<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->admin = User::factory()->create([
        'email' => 'admin@sis.ci',
        'password' => Hash::make('password'),
        'nom' => 'Admin',
        'prenom' => 'SIS',
    ]);
    $this->admin->assignRole('super-admin');
});

it('logs in with valid credentials', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'admin@sis.ci',
        'password' => 'password',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.user.email', 'admin@sis.ci')
        ->assertJsonStructure(['data' => ['token', 'user']]);
});

it('rejects invalid credentials', function () {
    $this->postJson('/api/v1/auth/login', [
        'email' => 'admin@sis.ci',
        'password' => 'wrong',
    ])->assertStatus(422);
});

it('returns authenticated user on me', function () {
    $this->actingAs($this->admin)
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.email', 'admin@sis.ci');
});

it('requires auth for me', function () {
    $this->getJson('/api/v1/auth/me')->assertUnauthorized();
});
