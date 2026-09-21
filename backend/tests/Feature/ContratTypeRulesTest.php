<?php

use App\Domain\Shared\Enums\TypeAgent;
use App\Models\Agent;
use App\Models\Contrat;
use App\Models\Grade;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->admin = User::factory()->create([
        'email' => 'admin-contrat-rules@sis.ci',
        'password' => Hash::make('password'),
    ]);
    $this->admin->assignRole('super-admin');

    $grade = Grade::query()->create([
        'libelle' => 'Agent Contrat',
        'type_agent' => TypeAgent::Agent,
    ]);

    $this->agent = Agent::query()->create([
        'grade_id' => $grade->id,
        'type' => TypeAgent::Agent,
        'nom' => 'Rules',
        'prenom' => 'Agent',
        'matricule' => 'AG-CTR-RULES',
        'statut' => 'disponible',
    ]);

    Contrat::query()->where('agent_id', $this->agent->id)->forceDelete();
});

it('rejects a CDD longer than 24 months', function () {
    $this->actingAs($this->admin)->postJson('/api/v1/contrats', [
        'agent_id' => $this->agent->id,
        'type' => 'cdd',
        'date_debut' => '2024-01-01',
        'date_fin' => '2026-02-01',
        'statut' => 'actif',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['date_fin']);
});

it('accepts a CDD within 24 months', function () {
    $response = $this->actingAs($this->admin)->postJson('/api/v1/contrats', [
        'agent_id' => $this->agent->id,
        'type' => 'cdd',
        'date_debut' => '2024-01-01',
        'date_fin' => '2025-12-31',
        'periode_essai_mois' => 1,
        'statut' => 'actif',
    ])->assertCreated();

    expect($response->json('data.duree_mois'))->toBe(23);
});

it('rejects CDI trial period above 6 months', function () {
    $this->actingAs($this->admin)->postJson('/api/v1/contrats', [
        'agent_id' => $this->agent->id,
        'type' => 'cdi',
        'date_debut' => '2026-01-01',
        'periode_essai_mois' => 7,
        'statut' => 'actif',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['periode_essai_mois']);
});

it('rejects trial period on stage contracts', function () {
    $this->actingAs($this->admin)->postJson('/api/v1/contrats', [
        'agent_id' => $this->agent->id,
        'type' => 'stage',
        'date_debut' => '2026-01-01',
        'date_fin' => '2026-06-30',
        'periode_essai_mois' => 1,
        'statut' => 'actif',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['periode_essai_mois']);
});

it('rejects a stage longer than 12 months', function () {
    $this->actingAs($this->admin)->postJson('/api/v1/contrats', [
        'agent_id' => $this->agent->id,
        'type' => 'stage',
        'date_debut' => '2026-01-01',
        'date_fin' => '2027-02-01',
        'statut' => 'actif',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['date_fin']);
});

it('accepts a stage of 3 or 6 months', function () {
    $this->actingAs($this->admin)->postJson('/api/v1/contrats', [
        'agent_id' => $this->agent->id,
        'type' => 'stage',
        'date_debut' => '2026-01-01',
        'date_fin' => '2026-04-01',
        'statut' => 'actif',
    ])->assertCreated();

    $other = Agent::query()->create([
        'grade_id' => $this->agent->grade_id,
        'type' => TypeAgent::Agent,
        'nom' => 'Stage6',
        'prenom' => 'Agent',
        'matricule' => 'AG-CTR-STG6',
        'statut' => 'disponible',
    ]);
    Contrat::query()->where('agent_id', $other->id)->forceDelete();

    $this->actingAs($this->admin)->postJson('/api/v1/contrats', [
        'agent_id' => $other->id,
        'type' => 'stage',
        'date_debut' => '2026-01-01',
        'date_fin' => '2026-07-01',
        'statut' => 'actif',
    ])->assertCreated();
});

it('rejects a prestation longer than 36 months', function () {
    $this->actingAs($this->admin)->postJson('/api/v1/contrats', [
        'agent_id' => $this->agent->id,
        'type' => 'prestation',
        'date_debut' => '2024-01-01',
        'date_fin' => '2027-02-01',
        'statut' => 'actif',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['date_fin']);
});
