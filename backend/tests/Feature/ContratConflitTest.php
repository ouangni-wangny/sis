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
        'email' => 'admin@sis.ci',
        'password' => Hash::make('password'),
    ]);
    $this->admin->assignRole('super-admin');

    $grade = Grade::query()->create([
        'libelle' => 'Agent',
        'type_agent' => TypeAgent::Agent,
    ]);

    $this->agent = Agent::query()->create([
        'grade_id' => $grade->id,
        'type' => TypeAgent::Agent,
        'nom' => 'Kone',
        'prenom' => 'Awa',
        'matricule' => 'AG-3001',
        'statut' => 'disponible',
    ]);

    Contrat::query()->where('agent_id', $this->agent->id)->forceDelete();
});

it('rejects a second overlapping active contract for the same agent', function () {
    $this->actingAs($this->admin)->postJson('/api/v1/contrats', [
        'agent_id' => $this->agent->id,
        'type' => 'cdi',
        'date_debut' => '2026-01-01',
        'statut' => 'actif',
    ])->assertCreated();

    $this->actingAs($this->admin)->postJson('/api/v1/contrats', [
        'agent_id' => $this->agent->id,
        'type' => 'cdd',
        'date_debut' => '2026-06-01',
        'date_fin' => '2026-12-31',
        'statut' => 'actif',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['agent_id']);
});

it('requires an end date for CDD contracts', function () {
    $this->actingAs($this->admin)->postJson('/api/v1/contrats', [
        'agent_id' => $this->agent->id,
        'type' => 'cdd',
        'date_debut' => '2026-01-01',
        'statut' => 'actif',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['date_fin']);
});

it('allows CDI after a terminated CDD for the same agent', function () {
    Contrat::query()->create([
        'agent_id' => $this->agent->id,
        'type' => 'cdd',
        'date_debut' => '2025-01-01',
        'date_fin' => '2025-12-31',
        'statut' => 'termine',
    ]);

    $this->actingAs($this->admin)->postJson('/api/v1/contrats', [
        'agent_id' => $this->agent->id,
        'type' => 'cdi',
        'date_debut' => '2026-01-01',
        'statut' => 'actif',
    ])->assertCreated()
        ->assertJsonPath('data.type', 'cdi');
});

it('updates statut and dates but keeps agent and type immutable', function () {
    $contrat = Contrat::query()->create([
        'agent_id' => $this->agent->id,
        'type' => 'cdd',
        'reference' => 'REF-1',
        'date_debut' => '2026-01-01',
        'date_fin' => '2026-06-30',
        'statut' => 'actif',
    ]);

    $this->actingAs($this->admin)->putJson("/api/v1/contrats/{$contrat->id}", [
        'statut' => 'termine',
        'date_fin' => '2026-05-31',
        'reference' => 'REF-1-CLOS',
        'agent_id' => 'should-be-ignored',
        'type' => 'cdi',
    ])->assertOk()
        ->assertJsonPath('data.statut', 'termine')
        ->assertJsonPath('data.reference', 'REF-1-CLOS')
        ->assertJsonPath('data.type', 'cdd');

    expect($contrat->fresh()->agent_id)->toBe($this->agent->id);
});
