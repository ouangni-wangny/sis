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
        'email' => 'admin@sis.ci',
        'password' => Hash::make('password'),
    ]);
    $this->admin->assignRole('super-admin');

    $client = Client::query()->create([
        'type' => 'entreprise',
        'raison_sociale' => 'Client Coverage',
        'statut' => 'actif',
    ]);
    $zone = Zone::query()->create(['nom' => 'Zone Coverage']);
    $this->site = Site::query()->create([
        'client_id' => $client->id,
        'zone_id' => $zone->id,
        'nom' => 'Site Coverage',
    ]);

    $this->poste = Poste::query()->create([
        'site_id' => $this->site->id,
        'nom' => 'Poste Test',
        'agents_requis' => 2,
    ]);

    $grade = Grade::query()->create([
        'libelle' => 'Agent Coverage',
        'type_agent' => TypeAgent::Agent,
    ]);
    $this->agent = Agent::query()->create([
        'grade_id' => $grade->id,
        'type' => TypeAgent::Agent,
        'nom' => 'Coverage',
        'prenom' => 'Agent',
        'matricule' => 'AG-COV-001',
        'statut' => 'disponible',
    ]);
});

it('reports sous_effectif when fewer agents are planned than required', function () {
    Vacation::query()->create([
        'agent_id' => $this->agent->id,
        'site_id' => $this->site->id,
        'poste_id' => $this->poste->id,
        'date_debut' => '2026-07-20',
        'heure_debut' => '08:00',
        'heure_fin' => '16:00',
        'statut' => 'planifiee',
    ]);

    $response = $this->actingAs($this->admin)
        ->getJson('/api/v1/postes/coverage?date=2026-07-20&site_id='.$this->site->id)
        ->assertOk();

    $response->assertJsonPath('data.0.poste_id', $this->poste->id)
        ->assertJsonPath('data.0.agents_requis', 2)
        ->assertJsonPath('data.0.planifies', 1)
        ->assertJsonPath('data.0.manquant', 1)
        ->assertJsonPath('data.0.statut', 'sous_effectif');
});

it('reports non_couvert when no agent is planned', function () {
    $response = $this->actingAs($this->admin)
        ->getJson('/api/v1/postes/coverage?date=2026-07-20&site_id='.$this->site->id)
        ->assertOk();

    $response->assertJsonPath('data.0.planifies', 0)
        ->assertJsonPath('data.0.statut', 'non_couvert');
});

it('reports overnight coverage on the start day, not the following morning', function () {
    Vacation::query()->create([
        'agent_id' => $this->agent->id,
        'site_id' => $this->site->id,
        'poste_id' => $this->poste->id,
        'date_debut' => '2026-07-20',
        'date_fin' => '2026-07-21',
        'heure_debut' => '20:00',
        'heure_fin' => '06:00',
        'statut' => 'planifiee',
    ]);

    $this->actingAs($this->admin)
        ->getJson('/api/v1/postes/coverage?date=2026-07-20&site_id='.$this->site->id)
        ->assertOk()
        ->assertJsonPath('data.0.planifies', 1);

    // Le lendemain matin ne recompte pas le même quart de nuit.
    $this->actingAs($this->admin)
        ->getJson('/api/v1/postes/coverage?date=2026-07-21&site_id='.$this->site->id)
        ->assertOk()
        ->assertJsonPath('data.0.planifies', 0);
});

it('flags sur_effectif when two day-shift agents fill a 24h poste missing its night shift', function () {
    $poste24h = Poste::query()->create([
        'site_id' => $this->site->id,
        'nom' => 'Poste 24h',
        'agents_requis' => 1,
        'heure_debut' => '07:00',
        'heure_fin' => '19:00',
        'heure_debut_nuit' => '19:00',
        'heure_fin_nuit' => '07:00',
    ]);

    $other = Agent::query()->create([
        'grade_id' => $this->agent->grade_id,
        'type' => TypeAgent::Agent,
        'nom' => 'Coverage2',
        'prenom' => 'Agent',
        'matricule' => 'AG-COV-002',
        'statut' => 'disponible',
    ]);

    // Two agents, both scheduled on the DAY shift — night is uncovered,
    // and day exceeds capacity (ceil(1/2)=1).
    Vacation::query()->create([
        'agent_id' => $this->agent->id,
        'site_id' => $this->site->id,
        'poste_id' => $poste24h->id,
        'date_debut' => '2026-07-20',
        'heure_debut' => '07:00',
        'heure_fin' => '19:00',
        'statut' => 'planifiee',
    ]);
    Vacation::query()->create([
        'agent_id' => $other->id,
        'site_id' => $this->site->id,
        'poste_id' => $poste24h->id,
        'date_debut' => '2026-07-20',
        'heure_debut' => '07:00',
        'heure_fin' => '19:00',
        'statut' => 'planifiee',
    ]);

    $response = $this->actingAs($this->admin)
        ->getJson('/api/v1/postes/coverage?date=2026-07-20&site_id='.$this->site->id)
        ->assertOk();

    $poste = collect($response->json('data'))->firstWhere('poste_id', $poste24h->id);

    expect($poste['couverture_24h'])->toBeTrue();
    expect($poste['jour']['planifies'])->toBe(2);
    expect($poste['nuit']['planifies'])->toBe(0);
    expect($poste['statut'])->toBe('sur_effectif');
});

it('reports ok for a 24h poste once both day and night shifts are covered', function () {
    $poste24h = Poste::query()->create([
        'site_id' => $this->site->id,
        'nom' => 'Poste 24h Complet',
        'agents_requis' => 2,
        'heure_debut' => '07:00',
        'heure_fin' => '19:00',
        'heure_debut_nuit' => '19:00',
        'heure_fin_nuit' => '07:00',
    ]);

    $night = Agent::query()->create([
        'grade_id' => $this->agent->grade_id,
        'type' => TypeAgent::Agent,
        'nom' => 'CoverageNuit',
        'prenom' => 'Agent',
        'matricule' => 'AG-COV-003',
        'statut' => 'disponible',
    ]);

    Vacation::query()->create([
        'agent_id' => $this->agent->id,
        'site_id' => $this->site->id,
        'poste_id' => $poste24h->id,
        'date_debut' => '2026-07-20',
        'heure_debut' => '07:00',
        'heure_fin' => '19:00',
        'statut' => 'planifiee',
    ]);
    Vacation::query()->create([
        'agent_id' => $night->id,
        'site_id' => $this->site->id,
        'poste_id' => $poste24h->id,
        'date_debut' => '2026-07-20',
        'heure_debut' => '19:00',
        'heure_fin' => '07:00',
        'statut' => 'planifiee',
    ]);

    $response = $this->actingAs($this->admin)
        ->getJson('/api/v1/postes/coverage?date=2026-07-20&site_id='.$this->site->id)
        ->assertOk();

    $poste = collect($response->json('data'))->firstWhere('poste_id', $poste24h->id);

    expect($poste['statut'])->toBe('ok');
    expect($poste['planifies'])->toBe(2);
    expect($poste['agents_requis'])->toBe(2);
    expect($poste['jour']['planifies'])->toBe(1);
    expect($poste['nuit']['planifies'])->toBe(1);
});

it('counts an open-ended vacation (no date_fin) as covering dates well beyond its start day', function () {
    Vacation::query()->create([
        'agent_id' => $this->agent->id,
        'site_id' => $this->site->id,
        'poste_id' => $this->poste->id,
        'date_debut' => '2026-07-20',
        'date_fin' => null,
        'heure_debut' => '08:00',
        'heure_fin' => '16:00',
        'statut' => 'planifiee',
    ]);

    $response = $this->actingAs($this->admin)
        ->getJson('/api/v1/postes/coverage?date=2026-09-01&site_id='.$this->site->id)
        ->assertOk();

    $response->assertJsonPath('data.0.planifies', 1);
});
