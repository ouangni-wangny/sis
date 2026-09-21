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
        'email' => 'admin-recouvrir@sis.ci',
        'password' => Hash::make('password'),
    ]);
    $this->admin->assignRole('super-admin');

    $grade = Grade::query()->create([
        'libelle' => 'Agent Recouvrir',
        'type_agent' => TypeAgent::Agent,
    ]);

    $this->agentAbsent = Agent::query()->create([
        'grade_id' => $grade->id,
        'type' => TypeAgent::Agent,
        'nom' => 'Absent',
        'prenom' => 'Agent',
        'matricule' => 'AG-REC-001',
        'statut' => 'disponible',
    ]);

    $this->remplacant = Agent::query()->create([
        'grade_id' => $grade->id,
        'type' => TypeAgent::Agent,
        'nom' => 'Remplacant',
        'prenom' => 'Agent',
        'matricule' => 'AG-REC-002',
        'statut' => 'disponible',
    ]);

    $client = Client::query()->create([
        'type' => 'entreprise',
        'raison_sociale' => 'Client Recouvrir',
        'statut' => 'actif',
    ]);
    $zone = Zone::query()->create(['nom' => 'Zone Recouvrir']);
    $this->site = Site::query()->create([
        'client_id' => $client->id,
        'zone_id' => $zone->id,
        'nom' => 'Site Recouvrir',
    ]);
    $this->poste = Poste::query()->create([
        'site_id' => $this->site->id,
        'nom' => 'Poste Accueil',
        'agents_requis' => 1,
        'heure_debut' => '08:00',
        'heure_fin' => '16:00',
    ]);
});

it('marks overlapping vacations a_recouvrir when an absence is approved', function () {
    $vacation = Vacation::query()->create([
        'agent_id' => $this->agentAbsent->id,
        'site_id' => $this->site->id,
        'poste_id' => $this->poste->id,
        'date_debut' => '2026-08-10',
        'date_fin' => '2026-08-10',
        'heure_debut' => '08:00',
        'heure_fin' => '16:00',
        'statut' => 'planifiee',
    ]);

    $this->actingAs($this->admin)->postJson('/api/v1/absences', [
        'agent_id' => $this->agentAbsent->id,
        'type' => 'conge',
        'date_debut' => '2026-08-10',
        'date_fin' => '2026-08-12',
        'motif' => 'Congé',
        'statut' => 'approuvee',
    ])->assertCreated();

    expect($vacation->fresh()->statut->value)->toBe('a_recouvrir');
});

it('does not mark the overnight shift of the day before an absence starts', function () {
    $nuitVeille = Vacation::query()->create([
        'agent_id' => $this->agentAbsent->id,
        'site_id' => $this->site->id,
        'poste_id' => $this->poste->id,
        'date_debut' => '2026-08-09',
        'date_fin' => '2026-08-10',
        'heure_debut' => '18:30',
        'heure_fin' => '06:30',
        'statut' => 'planifiee',
    ]);
    $nuitPremierJour = Vacation::query()->create([
        'agent_id' => $this->agentAbsent->id,
        'site_id' => $this->site->id,
        'poste_id' => $this->poste->id,
        'date_debut' => '2026-08-10',
        'date_fin' => '2026-08-11',
        'heure_debut' => '18:30',
        'heure_fin' => '06:30',
        'statut' => 'planifiee',
    ]);

    $this->actingAs($this->admin)->postJson('/api/v1/absences', [
        'agent_id' => $this->agentAbsent->id,
        'type' => 'conge',
        'date_debut' => '2026-08-10',
        'date_fin' => '2026-08-12',
        'motif' => 'Congé',
        'statut' => 'approuvee',
    ])->assertCreated();

    expect($nuitVeille->fresh()->statut->value)->toBe('planifiee');
    expect($nuitPremierJour->fresh()->statut->value)->toBe('a_recouvrir');
});

it('replaces an a_recouvrir vacation with a remplacant and cancels the hole', function () {
    $trou = Vacation::query()->create([
        'agent_id' => $this->agentAbsent->id,
        'site_id' => $this->site->id,
        'poste_id' => $this->poste->id,
        'date_debut' => '2026-08-15',
        'date_fin' => '2026-08-15',
        'heure_debut' => '08:00',
        'heure_fin' => '16:00',
        'statut' => 'a_recouvrir',
    ]);

    $response = $this->actingAs($this->admin)
        ->postJson('/api/v1/vacations/'.$trou->id.'/recouvrir', [
            'agent_id' => $this->remplacant->id,
        ])
        ->assertCreated();

    expect($response->json('data.agent_id'))->toBe($this->remplacant->id);
    expect($response->json('data.statut'))->toBe('planifiee');
    expect($trou->fresh()->statut->value)->toBe('annulee');
});

it('rejects recouvrir when vacation is not a_recouvrir', function () {
    $vacation = Vacation::query()->create([
        'agent_id' => $this->agentAbsent->id,
        'site_id' => $this->site->id,
        'poste_id' => $this->poste->id,
        'date_debut' => '2026-08-20',
        'date_fin' => '2026-08-20',
        'heure_debut' => '08:00',
        'heure_fin' => '16:00',
        'statut' => 'planifiee',
    ]);

    $this->actingAs($this->admin)
        ->postJson('/api/v1/vacations/'.$vacation->id.'/recouvrir', [
            'agent_id' => $this->remplacant->id,
        ])
        ->assertStatus(422);
});
