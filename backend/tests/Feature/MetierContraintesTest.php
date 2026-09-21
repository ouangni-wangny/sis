<?php

use App\Domain\Shared\Enums\StatutAbonnement;
use App\Domain\Shared\Enums\StatutRonde;
use App\Domain\Shared\Enums\TypeAgent;
use App\Models\Abonnement;
use App\Models\Agent;
use App\Models\Client;
use App\Models\Contrat;
use App\Models\Grade;
use App\Models\Offre;
use App\Models\Poste;
use App\Models\Ronde;
use App\Models\Site;
use App\Models\User;
use App\Models\Vacation;
use App\Models\Zone;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->admin = User::factory()->create([
        'email' => 'admin-metier@sis.ci',
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
        'nom' => 'Bamba',
        'prenom' => 'Iris',
        'matricule' => 'AG-MET-1',
        'statut' => 'disponible',
    ]);

    $this->controleur = Agent::query()->create([
        'grade_id' => $grade->id,
        'type' => TypeAgent::Controleur,
        'nom' => 'Diallo',
        'prenom' => 'Jean',
        'matricule' => 'RD-MET-1',
        'statut' => 'disponible',
    ]);

    $this->client = Client::query()->create([
        'type' => 'entreprise',
        'raison_sociale' => 'Client Metier',
        'statut' => 'actif',
    ]);
    $otherClient = Client::query()->create([
        'type' => 'entreprise',
        'raison_sociale' => 'Autre Client',
        'statut' => 'actif',
    ]);
    $zone = Zone::query()->create(['nom' => 'Zone Metier']);
    $this->site = Site::query()->create([
        'client_id' => $this->client->id,
        'zone_id' => $zone->id,
        'nom' => 'Site Metier',
        'latitude' => 5.35,
        'longitude' => -4.01,
        'rayon_metres' => 150,
    ]);
    $this->foreignSite = Site::query()->create([
        'client_id' => $otherClient->id,
        'zone_id' => $zone->id,
        'nom' => 'Site Autre',
    ]);
    $this->poste = Poste::query()->create([
        'site_id' => $this->site->id,
        'nom' => 'Poste A',
        'agents_requis' => 1,
    ]);
    $this->offre = Offre::query()->create([
        'libelle' => 'Offre active',
        'prix_mensuel' => 100000,
        'actif' => true,
    ]);
    $this->offreInactive = Offre::query()->create([
        'libelle' => 'Offre inactive',
        'prix_mensuel' => 50000,
        'actif' => false,
    ]);
});

it('rejects vacation when poste does not belong to site', function () {
    $otherPoste = Poste::query()->create([
        'site_id' => $this->foreignSite->id,
        'nom' => 'Poste B',
        'agents_requis' => 1,
    ]);

    $this->actingAs($this->admin)->postJson('/api/v1/vacations', [
        'agent_id' => $this->agent->id,
        'site_id' => $this->site->id,
        'poste_id' => $otherPoste->id,
        'date_debut' => '2026-07-20',
        'heure_debut' => '08:00',
        'heure_fin' => '16:00',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['poste_id']);
});

it('rejects deleting a vacation linked to an open ronde', function () {
    $vacation = Vacation::query()->create([
        'agent_id' => $this->controleur->id,
        'site_id' => $this->site->id,
        'date_debut' => '2026-07-20',
        'heure_debut' => '08:00',
        'heure_fin' => '16:00',
        'statut' => 'planifiee',
    ]);

    Ronde::query()->create([
        'agent_id' => $this->controleur->id,
        'site_id' => $this->site->id,
        'vacation_id' => $vacation->id,
        'statut' => StatutRonde::Planifiee,
    ]);

    $this->actingAs($this->admin)
        ->deleteJson('/api/v1/vacations/'.$vacation->id)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['vacation']);
});

it('rejects active abonnement with inactive offre or foreign site', function () {
    $this->actingAs($this->admin)->postJson('/api/v1/abonnements', [
        'client_id' => $this->client->id,
        'offre_id' => $this->offreInactive->id,
        'date_debut' => '2026-07-01',
        'periodicite' => 'mensuel',
        'statut' => StatutAbonnement::Actif->value,
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['offre_id']);

    $this->actingAs($this->admin)->postJson('/api/v1/abonnements', [
        'client_id' => $this->client->id,
        'offre_id' => $this->offre->id,
        'site_id' => $this->foreignSite->id,
        'date_debut' => '2026-07-01',
        'periodicite' => 'mensuel',
        'statut' => StatutAbonnement::Actif->value,
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['site_id']);
});

it('rejects controle without GPS on geolocalized site', function () {
    $this->actingAs($this->admin)->postJson('/api/v1/controles', [
        'agent_id' => $this->controleur->id,
        'controle_agent_id' => $this->agent->id,
        'site_id' => $this->site->id,
        'commentaire' => 'Sans GPS',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['latitude']);
});

it('rejects perimeter sync for non-rondier agents', function () {
    $this->actingAs($this->admin)->putJson('/api/v1/agents/'.$this->agent->id.'/perimetre', [
        'items' => [['zone_id' => $this->site->zone_id]],
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['agent_id']);
});

it('rejects deleting an active contrat', function () {
    $contrat = Contrat::query()->create([
        'agent_id' => $this->agent->id,
        'type' => 'cdi',
        'date_debut' => '2026-01-01',
        'statut' => 'actif',
    ]);

    $this->actingAs($this->admin)
        ->deleteJson('/api/v1/contrats/'.$contrat->id)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['statut']);
});

it('rejects self user deletion', function () {
    $this->actingAs($this->admin)
        ->deleteJson('/api/v1/users/'.$this->admin->id)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['user']);
});

it('rejects overlapping facture generation for same client period', function () {
    Vacation::query()->create([
        'agent_id' => $this->agent->id,
        'site_id' => $this->site->id,
        'date_debut' => '2026-07-10',
        'heure_debut' => '08:00',
        'heure_fin' => '16:00',
        'statut' => 'planifiee',
    ]);

    $this->actingAs($this->admin)->postJson('/api/v1/factures/generer', [
        'client_id' => $this->client->id,
        'date_debut' => '2026-07-01',
        'date_fin' => '2026-07-31',
    ])->assertSuccessful();

    $this->actingAs($this->admin)->postJson('/api/v1/factures/generer', [
        'client_id' => $this->client->id,
        'date_debut' => '2026-07-15',
        'date_fin' => '2026-08-15',
    ])->assertStatus(422);
});

it('rejects deleting offre with active abonnement', function () {
    Abonnement::query()->create([
        'client_id' => $this->client->id,
        'offre_id' => $this->offre->id,
        'date_debut' => '2026-07-01',
        'statut' => StatutAbonnement::Actif,
    ]);

    $this->actingAs($this->admin)
        ->deleteJson('/api/v1/offres/'.$this->offre->id)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['offre']);
});
