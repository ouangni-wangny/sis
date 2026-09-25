<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->developpeur = User::factory()->create([
        'email' => 'dev-roles@sis.ci',
        'password' => Hash::make('password'),
    ]);
    $this->developpeur->assignRole('developpeur');

    $this->admin = User::factory()->create([
        'email' => 'admin-roles@sis.ci',
        'password' => Hash::make('password'),
    ]);
    $this->admin->assignRole('super-admin');
});

it('autorise le super-admin à lister permissions et rôles', function () {
    $this->actingAs($this->admin)->getJson('/api/v1/system/permissions')->assertOk();
    $this->actingAs($this->admin)->getJson('/api/v1/system/roles')->assertOk();
});

it('masque les permissions system.* au super-admin', function () {
    $response = $this->actingAs($this->admin)->getJson('/api/v1/system/permissions');

    $response->assertOk();
    $names = collect($response->json('data'))->pluck('name');
    expect($names)->toContain('clients.view');
    expect($names)->not->toContain('system.roles.manage');
    expect($names)->not->toContain('system.features.manage');
});

it('liste toutes les permissions groupées pour un développeur', function () {
    $response = $this->actingAs($this->developpeur)->getJson('/api/v1/system/permissions');

    $response->assertOk();
    $names = collect($response->json('data'))->pluck('name');
    expect($names)->toContain('clients.view', 'system.roles.manage');
});

it('liste les rôles avec leurs permissions et marque les rôles protégés', function () {
    $response = $this->actingAs($this->developpeur)->getJson('/api/v1/system/roles');

    $response->assertOk();
    $roles = collect($response->json('data'))->keyBy('name');
    expect($roles->get('super-admin')['protected'])->toBeTrue();
    expect($roles->get('comptable')['protected'])->toBeTrue();
    expect($roles->get('super-admin')['permissions'])->toContain('clients.view', 'system.roles.manage');
});

it('permet au super-admin de créer un rôle personnalisé', function () {
    $response = $this->actingAs($this->admin)->postJson('/api/v1/system/roles', [
        'name' => 'auditeur-externe',
        'permissions' => ['dashboard.view', 'audit.view'],
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.name', 'auditeur-externe');
    $response->assertJsonPath('data.protected', false);

    $role = Role::query()->where('name', 'auditeur-externe')->firstOrFail();
    expect($role->permissions->pluck('name')->all())->toEqualCanonicalizing([
        'dashboard.view', 'audit.view',
    ]);
});

it('crée un rôle personnalisé avec un sous-ensemble de permissions', function () {
    $response = $this->actingAs($this->developpeur)->postJson('/api/v1/system/roles', [
        'name' => 'auditeur-externe',
        'permissions' => ['dashboard.view', 'audit.view'],
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.name', 'auditeur-externe');
    $response->assertJsonPath('data.protected', false);

    $role = Role::query()->where('name', 'auditeur-externe')->firstOrFail();
    expect($role->permissions->pluck('name')->all())->toEqualCanonicalizing([
        'dashboard.view', 'audit.view',
    ]);
});

it('rejette l’assignation de permissions system.* par un super-admin', function () {
    $this->actingAs($this->admin)->postJson('/api/v1/system/roles', [
        'name' => 'pseudo-dev',
        'permissions' => ['dashboard.view', 'system.features.manage'],
    ])->assertUnprocessable();
});

it('rejette un nom de rôle déjà utilisé ou mal formé', function () {
    $this->actingAs($this->developpeur)->postJson('/api/v1/system/roles', [
        'name' => 'super-admin',
        'permissions' => [],
    ])->assertUnprocessable();

    $this->actingAs($this->developpeur)->postJson('/api/v1/system/roles', [
        'name' => 'Invalide Nom',
        'permissions' => [],
    ])->assertUnprocessable();
});

it('met à jour les permissions d’un rôle personnalisé', function () {
    $role = Role::create(['name' => 'auditeur-externe', 'guard_name' => 'web']);
    $role->syncPermissions(['dashboard.view']);

    $response = $this->actingAs($this->admin)->patchJson("/api/v1/system/roles/{$role->id}", [
        'permissions' => ['dashboard.view', 'rapports.generate'],
    ]);

    $response->assertOk();
    expect($role->fresh()->permissions->pluck('name')->all())
        ->toEqualCanonicalizing(['dashboard.view', 'rapports.generate']);
});

it('préserve system.roles.manage sur super-admin quand l’admin synchronise', function () {
    $role = Role::query()->where('name', 'super-admin')->firstOrFail();

    $response = $this->actingAs($this->admin)->patchJson("/api/v1/system/roles/{$role->id}", [
        'permissions' => ['clients.view', 'users.view'],
    ]);

    $response->assertOk();
    expect($role->fresh()->permissions->pluck('name')->all())
        ->toContain('clients.view', 'users.view', 'system.roles.manage');
});

it('empêche le super-admin de modifier le rôle développeur', function () {
    $role = Role::query()->where('name', 'developpeur')->firstOrFail();

    $this->actingAs($this->admin)
        ->patchJson("/api/v1/system/roles/{$role->id}", [
            'permissions' => ['dashboard.view'],
        ])
        ->assertUnprocessable();
});

it('empêche de renommer ou supprimer un rôle protégé', function () {
    $role = Role::query()->where('name', 'operation')->firstOrFail();

    $this->actingAs($this->developpeur)
        ->patchJson("/api/v1/system/roles/{$role->id}", ['name' => 'operations-2'])
        ->assertUnprocessable();

    $this->actingAs($this->developpeur)
        ->deleteJson("/api/v1/system/roles/{$role->id}")
        ->assertUnprocessable();
});

it('empêche de supprimer un rôle encore assigné à un utilisateur', function () {
    $role = Role::create(['name' => 'auditeur-externe', 'guard_name' => 'web']);
    $this->admin->assignRole($role);

    $this->actingAs($this->developpeur)
        ->deleteJson("/api/v1/system/roles/{$role->id}")
        ->assertUnprocessable();
});

it('supprime un rôle personnalisé inutilisé', function () {
    $role = Role::create(['name' => 'auditeur-externe', 'guard_name' => 'web']);

    $this->actingAs($this->admin)
        ->deleteJson("/api/v1/system/roles/{$role->id}")
        ->assertNoContent();

    expect(Role::query()->where('name', 'auditeur-externe')->exists())->toBeFalse();
});
