<?php

use App\Domain\Shared\Enums\TypeAgent;
use App\Models\Agent;
use App\Models\Client;
use App\Models\Grade;
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

    $grade = Grade::query()->create([
        'libelle' => 'Agent Cascade',
        'type_agent' => TypeAgent::Agent,
    ]);
    $this->agent = Agent::query()->create([
        'grade_id' => $grade->id,
        'type' => TypeAgent::Agent,
        'nom' => 'Cascade',
        'prenom' => 'Agent',
        'matricule' => 'AG-CASC-001',
        'statut' => 'en_activite',
    ]);

    $client = Client::query()->create([
        'type' => 'entreprise',
        'raison_sociale' => 'Client Cascade',
        'statut' => 'actif',
    ]);
    $zone = Zone::query()->create(['nom' => 'Zone Cascade']);
    $this->site = Site::query()->create([
        'client_id' => $client->id,
        'zone_id' => $zone->id,
        'nom' => 'Site Cascade',
    ]);
});

it('marks future/ongoing vacations as a_recouvrir when the agent becomes unavailable', function () {
    $future = Vacation::query()->create([
        'agent_id' => $this->agent->id,
        'site_id' => $this->site->id,
        'date_debut' => now()->addDays(3)->toDateString(),
        'heure_debut' => '08:00',
        'heure_fin' => '16:00',
        'statut' => 'planifiee',
    ]);

    $ongoingOpenEnded = Vacation::query()->create([
        'agent_id' => $this->agent->id,
        'site_id' => $this->site->id,
        'date_debut' => now()->subDays(10)->toDateString(),
        'date_fin' => null,
        'heure_debut' => '08:00',
        'heure_fin' => '16:00',
        'statut' => 'en_cours',
    ]);

    $past = Vacation::query()->create([
        'agent_id' => $this->agent->id,
        'site_id' => $this->site->id,
        'date_debut' => now()->subDays(30)->toDateString(),
        'date_fin' => now()->subDays(29)->toDateString(),
        'heure_debut' => '08:00',
        'heure_fin' => '16:00',
        'statut' => 'terminee',
    ]);

    $this->actingAs($this->admin)
        ->putJson('/api/v1/agents/'.$this->agent->id, ['statut' => 'malade'])
        ->assertOk();

    expect($future->fresh()->statut->value)->toBe('a_recouvrir');
    expect($ongoingOpenEnded->fresh()->statut->value)->toBe('a_recouvrir');
    // Already-finished vacations are untouched — nothing to recover.
    expect($past->fresh()->statut->value)->toBe('terminee');
});

it('does not touch vacations when the agent statut change keeps them available', function () {
    $vacation = Vacation::query()->create([
        'agent_id' => $this->agent->id,
        'site_id' => $this->site->id,
        'date_debut' => now()->addDays(1)->toDateString(),
        'heure_debut' => '08:00',
        'heure_fin' => '16:00',
        'statut' => 'planifiee',
    ]);

    $this->actingAs($this->admin)
        ->putJson('/api/v1/agents/'.$this->agent->id, ['statut' => 'disponible'])
        ->assertOk();

    expect($vacation->fresh()->statut->value)->toBe('planifiee');
});
