<?php

use App\Domain\Shared\Enums\StatutRonde;
use App\Domain\Shared\Enums\TypeAgent;
use App\Models\Agent;
use App\Models\Checkpoint;
use App\Models\Client;
use App\Models\Grade;
use App\Models\Ronde;
use App\Models\Site;
use App\Models\User;
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
        'libelle' => 'Contrôleur',
        'type_agent' => TypeAgent::Controleur,
    ]);

    $this->controleur = Agent::query()->create([
        'grade_id' => $grade->id,
        'type' => TypeAgent::Controleur,
        'nom' => 'Kone',
        'prenom' => 'Mohamed',
        'matricule' => 'RD-9001',
        'statut' => 'disponible',
    ]);

    $this->agentPoste = Agent::query()->create([
        'grade_id' => $grade->id,
        'type' => TypeAgent::Agent,
        'nom' => 'Traore',
        'prenom' => 'Awa',
        'matricule' => 'AG-9001',
        'statut' => 'disponible',
    ]);

    $client = Client::query()->create([
        'type' => 'entreprise',
        'raison_sociale' => 'Client Ronde',
        'statut' => 'actif',
    ]);
    $zone = Zone::query()->create(['nom' => 'Zone Ronde']);
    $this->site = Site::query()->create([
        'client_id' => $client->id,
        'zone_id' => $zone->id,
        'nom' => 'Site Ronde',
        'latitude' => 5.3,
        'longitude' => -4.0,
    ]);

    Checkpoint::query()->create([
        'site_id' => $this->site->id,
        'nom' => 'Entrée',
        'code_qr' => 'QR-ENTREE',
        'ordre' => 1,
    ]);
});

it('rejects planning a ronde for a non-rondier agent', function () {
    $this->actingAs($this->admin)->postJson('/api/v1/rondes', [
        'agent_id' => $this->agentPoste->id,
        'site_id' => $this->site->id,
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['agent_id']);
});

it('rejects a second open ronde for the same rondier', function () {
    Ronde::query()->create([
        'agent_id' => $this->controleur->id,
        'site_id' => $this->site->id,
        'statut' => StatutRonde::EnCours,
    ]);

    $this->actingAs($this->admin)->postJson('/api/v1/rondes', [
        'agent_id' => $this->controleur->id,
        'site_id' => $this->site->id,
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['agent_id']);
});

it('rejects a site without checkpoints', function () {
    $siteVide = Site::query()->create([
        'client_id' => $this->site->client_id,
        'zone_id' => $this->site->zone_id,
        'nom' => 'Site sans checkpoint',
        'latitude' => 5.4,
        'longitude' => -4.1,
    ]);

    $this->actingAs($this->admin)->postJson('/api/v1/rondes', [
        'agent_id' => $this->controleur->id,
        'site_id' => $siteVide->id,
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['site_id']);
});

it('plans a ronde when constraints are satisfied', function () {
    $this->actingAs($this->admin)->postJson('/api/v1/rondes', [
        'agent_id' => $this->controleur->id,
        'site_id' => $this->site->id,
    ])->assertCreated()
        ->assertJsonPath('data.statut', 'planifiee');
});
