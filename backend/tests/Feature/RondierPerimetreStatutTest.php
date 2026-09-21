<?php

use App\Domain\Shared\Enums\TypeAgent;
use App\Models\Agent;
use App\Models\Grade;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->admin = User::factory()->create([
        'email' => 'admin-perimetre@sis.ci',
        'password' => Hash::make('password'),
    ]);
    $this->admin->assignRole('super-admin');

    $grade = Grade::query()->create([
        'libelle' => 'Contrôleur',
        'type_agent' => TypeAgent::Controleur,
    ]);

    $this->controleur = Agent::query()->create([
        'grade_id' => $grade->id,
        'type' => TypeAgent::Controleur,
        'nom' => 'Kone',
        'prenom' => 'Ibrahim',
        'matricule' => 'RD-PERIM-1',
        'statut' => 'disponible',
    ]);

    $this->zone = Zone::query()->create(['nom' => 'Zone Perimetre Statut']);
});

it('marks a rondier en_activite when a perimetre is assigned', function () {
    $this->actingAs($this->admin)->putJson(
        '/api/v1/agents/'.$this->controleur->id.'/perimetre',
        ['items' => [['zone_id' => $this->zone->id]]]
    )->assertOk()->assertJsonPath('data.statut', 'en_activite');

    expect($this->controleur->fresh()->statut->value)->toBe('en_activite');
});

it('marks a rondier disponible again when the perimetre is released', function () {
    $this->actingAs($this->admin)->putJson(
        '/api/v1/agents/'.$this->controleur->id.'/perimetre',
        ['items' => [['zone_id' => $this->zone->id]]]
    )->assertOk();

    $this->actingAs($this->admin)->putJson(
        '/api/v1/agents/'.$this->controleur->id.'/perimetre',
        ['items' => []]
    )->assertOk()->assertJsonPath('data.statut', 'disponible');

    expect($this->controleur->fresh()->statut->value)->toBe('disponible');
});

it('does not reactivate a suspended rondier through perimetre assignment', function () {
    $this->controleur->update(['statut' => 'suspendu']);

    $this->actingAs($this->admin)->putJson(
        '/api/v1/agents/'.$this->controleur->id.'/perimetre',
        ['items' => [['zone_id' => $this->zone->id]]]
    )->assertOk()->assertJsonPath('data.statut', 'suspendu');

    expect($this->controleur->fresh()->statut->value)->toBe('suspendu');
});
