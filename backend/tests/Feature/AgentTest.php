<?php

use App\Domain\Shared\Enums\TypeAgent;
use App\Models\Agent;
use App\Models\Grade;
use App\Models\User;
use App\Models\Ville;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->admin = User::factory()->create([
        'email' => 'admin@sis.ci',
        'password' => Hash::make('password'),
    ]);
    $this->admin->assignRole('super-admin');

    $this->grade = Grade::query()->create([
        'libelle' => 'Contrôleur',
        'type_agent' => TypeAgent::Controleur,
    ]);

    $this->ville = Ville::query()->create([
        'libelle' => 'Abidjan',
    ]);
});

it('creates agent with auto matricule and one-shot pin', function () {
    $response = $this->actingAs($this->admin)->postJson('/api/v1/agents', [
        'grade_id' => $this->grade->id,
        'nom' => 'Kouassi',
        'prenom' => 'Jean',
        'pin' => '4321',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.matricule', '0000A')
        ->assertJsonPath('data.plain_pin', '4321')
        ->assertJsonPath('data.type', 'controleur');

    $agent = Agent::query()->first();
    expect($agent->user_id)->not->toBeNull();
    expect($agent->user->matricule)->toBe('0000A');
    expect($agent->user->hasRole('controleur'))->toBeTrue();
});

it('increments matricule sequentially', function () {
    Agent::query()->create([
        'grade_id' => $this->grade->id,
        'type' => TypeAgent::Controleur,
        'nom' => 'Existant',
        'prenom' => 'Test',
        'matricule' => '0000A',
    ]);

    $response = $this->actingAs($this->admin)->postJson('/api/v1/agents', [
        'grade_id' => $this->grade->id,
        'nom' => 'Diallo',
        'prenom' => 'Fatou',
        'civilite' => 'madame',
        'nationalite' => 'Ivoirienne',
        'ville_id' => $this->ville->id,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.matricule', '0001A')
        ->assertJsonPath('data.civilite', 'madame')
        ->assertJsonPath('data.ville_id', $this->ville->id)
        ->assertJsonPath('data.ville', 'Abidjan');
});

it('rolls matricule letter after 9999', function () {
    Agent::query()->create([
        'grade_id' => $this->grade->id,
        'type' => TypeAgent::Controleur,
        'nom' => 'Dernier',
        'prenom' => 'A',
        'matricule' => '9999A',
    ]);

    $response = $this->actingAs($this->admin)->postJson('/api/v1/agents', [
        'grade_id' => $this->grade->id,
        'nom' => 'Premier',
        'prenom' => 'B',
    ]);

    $response->assertCreated()->assertJsonPath('data.matricule', '0000B');
});

it('hides administration agents from the operation role in the list and on direct access', function () {
    $operationUser = User::factory()->create([
        'email' => 'operation2@sis.ci',
        'password' => Hash::make('password'),
    ]);
    $operationUser->assignRole('operation');

    $controleur = Agent::query()->create([
        'grade_id' => $this->grade->id,
        'type' => TypeAgent::Controleur,
        'nom' => 'Visible',
        'prenom' => 'Par',
        'matricule' => '0000A',
    ]);

    $administratif = Agent::query()->create([
        'grade_id' => $this->grade->id,
        'type' => TypeAgent::Administration,
        'nom' => 'Caché',
        'prenom' => 'Pour',
        'matricule' => '0000B',
    ]);

    $response = $this->actingAs($operationUser)->getJson('/api/v1/agents');

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id');
    expect($ids)->toContain($controleur->id);
    expect($ids)->not->toContain($administratif->id);

    $this->actingAs($operationUser)
        ->getJson("/api/v1/agents/{$administratif->id}")
        ->assertForbidden();

    $this->actingAs($operationUser)
        ->getJson("/api/v1/agents/{$controleur->id}")
        ->assertOk();
});
