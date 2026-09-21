<?php

use App\Domain\Shared\Enums\TypeAgent;
use App\Models\Agent;
use App\Models\Client;
use App\Models\Grade;
use App\Models\Poste;
use App\Models\Site;
use App\Models\User;
use App\Models\Vacation;
use App\Models\Zone;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->admin = User::factory()->create([
        'email' => 'admin-absence-wf@sis.ci',
        'password' => Hash::make('password'),
    ]);
    $this->admin->assignRole('super-admin');

    $grade = Grade::query()->create([
        'libelle' => 'Agent Absence WF',
        'type_agent' => TypeAgent::Agent,
    ]);

    $this->agent = Agent::query()->create([
        'grade_id' => $grade->id,
        'type' => TypeAgent::Agent,
        'nom' => 'Kone',
        'prenom' => 'Awa',
        'matricule' => 'AG-ABS-WF-001',
        'statut' => 'disponible',
    ]);

    $client = Client::query()->create([
        'type' => 'entreprise',
        'raison_sociale' => 'Client Absence WF',
        'statut' => 'actif',
    ]);
    $zone = Zone::query()->create(['nom' => 'Zone Absence WF']);
    $this->site = Site::query()->create([
        'client_id' => $client->id,
        'zone_id' => $zone->id,
        'nom' => 'Site Absence WF',
    ]);
    $this->poste = Poste::query()->create([
        'site_id' => $this->site->id,
        'nom' => 'Poste Accueil',
        'agents_requis' => 1,
        'heure_debut' => '08:00',
        'heure_fin' => '16:00',
    ]);
});

function createVacationForAbsenceWorkflow(object $ctx, string $date = '2026-08-10'): Vacation
{
    return Vacation::query()->create([
        'agent_id' => $ctx->agent->id,
        'site_id' => $ctx->site->id,
        'poste_id' => $ctx->poste->id,
        'date_debut' => $date,
        'date_fin' => $date,
        'heure_debut' => '08:00',
        'heure_fin' => '16:00',
        'statut' => 'planifiee',
    ]);
}

it('creates a pending absence without planning impact', function () {
    $vacation = createVacationForAbsenceWorkflow($this);

    $response = $this->actingAs($this->admin)->postJson('/api/v1/absences', [
        'agent_id' => $this->agent->id,
        'type' => 'conge',
        'date_debut' => '2026-08-10',
        'date_fin' => '2026-08-12',
        'motif' => 'Congé',
        'statut' => 'en_attente',
    ])->assertCreated();

    expect($response->json('data.statut'))->toBe('en_attente');
    expect($response->json('data.type'))->toBe('conge');
    expect($vacation->fresh()->statut->value)->toBe('planifiee');
    expect($this->agent->fresh()->statut->value)->toBe('disponible');
});

it('approves an absence and marks vacations a_recouvrir with absence_id', function () {
    $vacation = createVacationForAbsenceWorkflow($this);

    $response = $this->actingAs($this->admin)->postJson('/api/v1/absences', [
        'agent_id' => $this->agent->id,
        'type' => 'maladie',
        'date_debut' => '2026-08-10',
        'date_fin' => '2026-08-12',
        'motif' => 'Grippe',
        'statut' => 'approuvee',
    ])->assertCreated();

    $absenceId = $response->json('data.id');

    expect($response->json('data.vacations_marquees_a_recouvrir'))->toBe(1);
    expect($vacation->fresh()->statut->value)->toBe('a_recouvrir');
    expect($vacation->fresh()->absence_id)->toBe($absenceId);
    expect($this->agent->fresh()->statut->value)->toBe('malade');
});

it('rejects overlapping absences', function () {
    $this->actingAs($this->admin)->postJson('/api/v1/absences', [
        'agent_id' => $this->agent->id,
        'type' => 'conge',
        'date_debut' => '2026-08-10',
        'date_fin' => '2026-08-12',
        'statut' => 'en_attente',
    ])->assertCreated();

    $this->actingAs($this->admin)->postJson('/api/v1/absences', [
        'agent_id' => $this->agent->id,
        'type' => 'permission',
        'date_debut' => '2026-08-11',
        'date_fin' => '2026-08-13',
        'statut' => 'en_attente',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['agent_id']);
});

it('transitions en_attente to approuvee via update', function () {
    $vacation = createVacationForAbsenceWorkflow($this);

    $create = $this->actingAs($this->admin)->postJson('/api/v1/absences', [
        'agent_id' => $this->agent->id,
        'type' => 'conge',
        'date_debut' => '2026-08-10',
        'date_fin' => '2026-08-12',
        'statut' => 'en_attente',
    ])->assertCreated();

    $response = $this->actingAs($this->admin)->putJson(
        '/api/v1/absences/'.$create->json('data.id'),
        ['statut' => 'approuvee'],
    )->assertOk();

    expect($response->json('data.vacations_marquees_a_recouvrir'))->toBe(1);
    expect($vacation->fresh()->statut->value)->toBe('a_recouvrir');
    expect($this->agent->fresh()->statut->value)->toBe('conge');
});

it('blocks invalid status transitions', function () {
    $create = $this->actingAs($this->admin)->postJson('/api/v1/absences', [
        'agent_id' => $this->agent->id,
        'type' => 'conge',
        'date_debut' => '2026-08-10',
        'date_fin' => '2026-08-12',
        'statut' => 'en_attente',
    ])->assertCreated();

    $this->actingAs($this->admin)->putJson(
        '/api/v1/absences/'.$create->json('data.id'),
        ['statut' => 'approuvee'],
    )->assertOk();

    $this->actingAs($this->admin)->putJson(
        '/api/v1/absences/'.$create->json('data.id'),
        ['statut' => 'refusee'],
    )->assertStatus(422)
        ->assertJsonValidationErrors(['statut']);
});

it('restores vacations and agent statut when cancelling an approved absence', function () {
    $vacation = createVacationForAbsenceWorkflow($this);

    $create = $this->actingAs($this->admin)->postJson('/api/v1/absences', [
        'agent_id' => $this->agent->id,
        'type' => 'maladie',
        'date_debut' => '2026-08-10',
        'date_fin' => '2026-08-12',
        'statut' => 'approuvee',
    ])->assertCreated();

    $response = $this->actingAs($this->admin)->putJson(
        '/api/v1/absences/'.$create->json('data.id'),
        ['statut' => 'annulee'],
    )->assertOk();

    expect($response->json('data.vacations_restaurees'))->toBe(1);
    expect($vacation->fresh()->statut->value)->toBe('planifiee');
    expect($vacation->fresh()->absence_id)->toBeNull();
    expect($this->agent->fresh()->statut->value)->toBe('disponible');
});

it('restores vacations when deleting an approved absence', function () {
    $vacation = createVacationForAbsenceWorkflow($this);

    $create = $this->actingAs($this->admin)->postJson('/api/v1/absences', [
        'agent_id' => $this->agent->id,
        'type' => 'conge',
        'date_debut' => '2026-08-10',
        'date_fin' => '2026-08-12',
        'statut' => 'approuvee',
    ])->assertCreated();

    $response = $this->actingAs($this->admin)
        ->deleteJson('/api/v1/absences/'.$create->json('data.id'))
        ->assertOk();

    expect($response->json('data.vacations_restaurees'))->toBe(1);
    expect($vacation->fresh()->statut->value)->toBe('planifiee');
});

it('prevents editing a refused absence', function () {
    $create = $this->actingAs($this->admin)->postJson('/api/v1/absences', [
        'agent_id' => $this->agent->id,
        'type' => 'permission',
        'date_debut' => '2026-08-10',
        'date_fin' => '2026-08-12',
        'statut' => 'en_attente',
    ])->assertCreated();

    $this->actingAs($this->admin)->putJson(
        '/api/v1/absences/'.$create->json('data.id'),
        ['statut' => 'refusee'],
    )->assertOk();

    $this->actingAs($this->admin)->putJson(
        '/api/v1/absences/'.$create->json('data.id'),
        ['motif' => 'Tentative'],
    )->assertStatus(422)
        ->assertJsonValidationErrors(['statut']);
});

it('rejects creating absence with terminal status', function () {
    $this->actingAs($this->admin)->postJson('/api/v1/absences', [
        'agent_id' => $this->agent->id,
        'type' => 'autre',
        'date_debut' => '2026-08-10',
        'date_fin' => '2026-08-12',
        'statut' => 'refusee',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['statut']);
});
