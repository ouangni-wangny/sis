<?php

use App\Domain\Shared\Enums\TypeAgent;
use App\Models\Agent;
use App\Models\Client;
use App\Models\Grade;
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
        'libelle' => 'Agent',
        'type_agent' => TypeAgent::Agent,
    ]);

    $this->agent = Agent::query()->create([
        'grade_id' => $grade->id,
        'type' => TypeAgent::Agent,
        'nom' => 'Traore',
        'prenom' => 'Amadou',
        'matricule' => 'AG-2001',
        'statut' => 'disponible',
    ]);

    $client = Client::query()->create([
        'type' => 'entreprise',
        'raison_sociale' => 'Client Vac',
        'statut' => 'actif',
    ]);
    $zone = Zone::query()->create(['nom' => 'Zone Test']);
    $this->site = Site::query()->create([
        'client_id' => $client->id,
        'zone_id' => $zone->id,
        'nom' => 'Site Test',
        'latitude' => 5.3,
        'longitude' => -4.0,
    ]);
});

it('detects vacation conflicts with 409', function () {
    $this->actingAs($this->admin)->postJson('/api/v1/vacations', [
        'agent_id' => $this->agent->id,
        'site_id' => $this->site->id,
        'date_debut' => '2026-07-20',
        'heure_debut' => '08:00',
        'heure_fin' => '16:00',
    ])->assertCreated();

    $this->actingAs($this->admin)->postJson('/api/v1/vacations', [
        'agent_id' => $this->agent->id,
        'site_id' => $this->site->id,
        'date_debut' => '2026-07-20',
        'heure_debut' => '12:00',
        'heure_fin' => '20:00',
    ])->assertStatus(409)
        ->assertJsonPath('error', 'VacationConflitException');
});

it('detects conflicts across overnight shifts', function () {
    // Night shift 20:00 -> 06:00: effectively occupies the agent until 06:00 the next day.
    $this->actingAs($this->admin)->postJson('/api/v1/vacations', [
        'agent_id' => $this->agent->id,
        'site_id' => $this->site->id,
        'date_debut' => '2026-07-20',
        'heure_debut' => '20:00',
        'heure_fin' => '06:00',
    ])->assertCreated();

    // A shift starting at 05:00 the following morning overlaps with the tail
    // end of the overnight shift (which really ends at 06:00 on 2026-07-21).
    $this->actingAs($this->admin)->postJson('/api/v1/vacations', [
        'agent_id' => $this->agent->id,
        'site_id' => $this->site->id,
        'date_debut' => '2026-07-21',
        'heure_debut' => '05:00',
        'heure_fin' => '09:00',
    ])->assertStatus(409)
        ->assertJsonPath('error', 'VacationConflitException');
});

it('allows updating a vacation statut via PUT', function () {
    $response = $this->actingAs($this->admin)->postJson('/api/v1/vacations', [
        'agent_id' => $this->agent->id,
        'site_id' => $this->site->id,
        'date_debut' => '2026-07-22',
        'heure_debut' => '08:00',
        'heure_fin' => '16:00',
    ])->assertCreated();

    $vacationId = $response->json('data.id');

    $this->actingAs($this->admin)
        ->putJson("/api/v1/vacations/{$vacationId}", ['statut' => 'annulee'])
        ->assertOk()
        ->assertJsonPath('data.statut', 'annulee');
});

it('does not flag a shift right after an overnight shift ends as a conflict', function () {
    $this->actingAs($this->admin)->postJson('/api/v1/vacations', [
        'agent_id' => $this->agent->id,
        'site_id' => $this->site->id,
        'date_debut' => '2026-07-20',
        'heure_debut' => '20:00',
        'heure_fin' => '06:00',
    ])->assertCreated();

    // Starts 11h after the overnight shift ends (2026-07-21 06:00 + 11h
    // rest = 17:00): no overlap, and meets the minimum rest requirement.
    $this->actingAs($this->admin)->postJson('/api/v1/vacations', [
        'agent_id' => $this->agent->id,
        'site_id' => $this->site->id,
        'date_debut' => '2026-07-21',
        'heure_debut' => '17:00',
        'heure_fin' => '23:00',
    ])->assertCreated();
});

it('treats an open-ended vacation (no date_fin) as ongoing, not a single day', function () {
    $this->actingAs($this->admin)->postJson('/api/v1/vacations', [
        'agent_id' => $this->agent->id,
        'site_id' => $this->site->id,
        'date_debut' => '2026-07-20',
        'heure_debut' => '08:00',
        'heure_fin' => '16:00',
    ])->assertCreated();

    // Months later, same agent, same hours: must still conflict, since the
    // first vacation has no end date and is meant to be ongoing.
    $this->actingAs($this->admin)->postJson('/api/v1/vacations', [
        'agent_id' => $this->agent->id,
        'site_id' => $this->site->id,
        'date_debut' => '2026-09-01',
        'heure_debut' => '08:00',
        'heure_fin' => '16:00',
    ])->assertStatus(409);
});

it('rejects a vacation longer than the configured max duration', function () {
    $this->actingAs($this->admin)->postJson('/api/v1/vacations', [
        'agent_id' => $this->agent->id,
        'site_id' => $this->site->id,
        'date_debut' => '2026-07-20',
        'heure_debut' => '06:00',
        'heure_fin' => '20:00',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['heure_fin']);
});

it('rejects a second agent when poste effectif is already full', function () {
    $poste = \App\Models\Poste::query()->create([
        'site_id' => $this->site->id,
        'nom' => 'Entrée',
        'agents_requis' => 1,
        'heure_debut' => '08:00',
        'heure_fin' => '18:00',
    ]);

    $other = Agent::query()->create([
        'grade_id' => $this->agent->grade_id,
        'type' => TypeAgent::Agent,
        'nom' => 'Kone',
        'prenom' => 'Bema',
        'matricule' => 'AG-2002',
        'statut' => 'disponible',
    ]);

    $this->actingAs($this->admin)->postJson('/api/v1/vacations', [
        'agent_id' => $this->agent->id,
        'site_id' => $this->site->id,
        'poste_id' => $poste->id,
        'date_debut' => '2026-07-20',
        'date_fin' => '2026-07-20',
        'heure_debut' => '08:00',
        'heure_fin' => '18:00',
    ])->assertCreated();

    $this->actingAs($this->admin)->postJson('/api/v1/vacations', [
        'agent_id' => $other->id,
        'site_id' => $this->site->id,
        'poste_id' => $poste->id,
        'date_debut' => '2026-07-20',
        'date_fin' => '2026-07-20',
        'heure_debut' => '08:00',
        'heure_fin' => '18:00',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['poste_id']);
});

it('rejects a second agent on the same day shift of a 24h poste with effectif 2', function () {
    $poste = \App\Models\Poste::query()->create([
        'site_id' => $this->site->id,
        'nom' => 'Poste 24h',
        'agents_requis' => 2,
        'heure_debut' => '07:00',
        'heure_fin' => '19:00',
        'heure_debut_nuit' => '19:00',
        'heure_fin_nuit' => '07:00',
    ]);

    $other = Agent::query()->create([
        'grade_id' => $this->agent->grade_id,
        'type' => TypeAgent::Agent,
        'nom' => 'Kone',
        'prenom' => 'Bema',
        'matricule' => 'AG-2003',
        'statut' => 'disponible',
    ]);

    $this->actingAs($this->admin)->postJson('/api/v1/vacations', [
        'agent_id' => $this->agent->id,
        'site_id' => $this->site->id,
        'poste_id' => $poste->id,
        'date_debut' => '2026-07-20',
        'date_fin' => '2026-07-20',
        'heure_debut' => '07:00',
        'heure_fin' => '19:00',
    ])->assertCreated();

    // Même créneau jour → refusé (capacité 1 par quart pour effectif 2 en 24h)
    $this->actingAs($this->admin)->postJson('/api/v1/vacations', [
        'agent_id' => $other->id,
        'site_id' => $this->site->id,
        'poste_id' => $poste->id,
        'date_debut' => '2026-07-20',
        'date_fin' => '2026-07-20',
        'heure_debut' => '07:00',
        'heure_fin' => '19:00',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['poste_id']);

    // Quart nuit → accepté
    $this->actingAs($this->admin)->postJson('/api/v1/vacations', [
        'agent_id' => $other->id,
        'site_id' => $this->site->id,
        'poste_id' => $poste->id,
        'date_debut' => '2026-07-20',
        'date_fin' => '2026-07-21',
        'heure_debut' => '19:00',
        'heure_fin' => '07:00',
    ])->assertCreated();
});

it('allows two agents together on a 24h cycle poste (no invented quarts)', function () {
    $poste = \App\Models\Poste::query()->create([
        'site_id' => $this->site->id,
        'nom' => 'Cycle Portail',
        'agents_requis' => 2,
        'mode_effectif' => 'ensemble',
        'heure_debut' => '06:30',
        'heure_fin' => '06:30',
        'heure_debut_nuit' => null,
        'heure_fin_nuit' => null,
    ]);

    $other = Agent::query()->create([
        'grade_id' => $this->agent->grade_id,
        'type' => TypeAgent::Agent,
        'nom' => 'Dialli',
        'prenom' => 'Rom',
        'matricule' => 'AG-2004',
        'statut' => 'disponible',
    ]);

    // Deux agents sur le même cycle, même jour — ensemble (effectif 2).
    $this->actingAs($this->admin)->postJson('/api/v1/vacations', [
        'agent_id' => $this->agent->id,
        'site_id' => $this->site->id,
        'poste_id' => $poste->id,
        'date_debut' => '2026-07-25',
        'date_fin' => '2026-07-25',
        'heure_debut' => '06:30',
        'heure_fin' => '06:30',
    ])->assertCreated();

    $this->actingAs($this->admin)->postJson('/api/v1/vacations', [
        'agent_id' => $other->id,
        'site_id' => $this->site->id,
        'poste_id' => $poste->id,
        'date_debut' => '2026-07-25',
        'date_fin' => '2026-07-25',
        'heure_debut' => '06:30',
        'heure_fin' => '06:30',
    ])->assertCreated();

    $third = Agent::query()->create([
        'grade_id' => $this->agent->grade_id,
        'type' => TypeAgent::Agent,
        'nom' => 'Trop',
        'prenom' => 'Plein',
        'matricule' => 'AG-2004b',
        'statut' => 'disponible',
    ]);

    // 3ᵉ agent le même jour → capacité 2 atteinte.
    $this->actingAs($this->admin)->postJson('/api/v1/vacations', [
        'agent_id' => $third->id,
        'site_id' => $this->site->id,
        'poste_id' => $poste->id,
        'date_debut' => '2026-07-25',
        'date_fin' => '2026-07-25',
        'heure_debut' => '06:30',
        'heure_fin' => '06:30',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['poste_id']);
});

it('rejects a second agent same day on a cycle poste in mode alternance', function () {
    $poste = \App\Models\Poste::query()->create([
        'site_id' => $this->site->id,
        'nom' => 'Cycle Alternance',
        'agents_requis' => 2,
        'mode_effectif' => 'alternance',
        'heure_debut' => '06:30',
        'heure_fin' => '06:30',
        'heure_debut_nuit' => null,
        'heure_fin_nuit' => null,
    ]);

    $other = Agent::query()->create([
        'grade_id' => $this->agent->grade_id,
        'type' => TypeAgent::Agent,
        'nom' => 'Alter',
        'prenom' => 'Nance',
        'matricule' => 'AG-2004c',
        'statut' => 'disponible',
    ]);

    $this->actingAs($this->admin)->postJson('/api/v1/vacations', [
        'agent_id' => $this->agent->id,
        'site_id' => $this->site->id,
        'poste_id' => $poste->id,
        'date_debut' => '2026-07-26',
        'date_fin' => '2026-07-26',
        'heure_debut' => '06:30',
        'heure_fin' => '06:30',
    ])->assertCreated();

    // Capacité journalière = 1 en alternance.
    $this->actingAs($this->admin)->postJson('/api/v1/vacations', [
        'agent_id' => $other->id,
        'site_id' => $this->site->id,
        'poste_id' => $poste->id,
        'date_debut' => '2026-07-26',
        'date_fin' => '2026-07-26',
        'heure_debut' => '06:30',
        'heure_fin' => '06:30',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['poste_id']);

    // Jour complémentaire → OK.
    $this->actingAs($this->admin)->postJson('/api/v1/vacations', [
        'agent_id' => $other->id,
        'site_id' => $this->site->id,
        'poste_id' => $poste->id,
        'date_debut' => '2026-07-27',
        'date_fin' => '2026-07-27',
        'heure_debut' => '06:30',
        'heure_fin' => '06:30',
    ])->assertCreated();
});

it('rejects a day shift when an open-ended same-half vacation already covers that day', function () {
    $poste = \App\Models\Poste::query()->create([
        'site_id' => $this->site->id,
        'nom' => 'Entrée ouverte',
        'agents_requis' => 1,
        'heure_debut' => '08:00',
        'heure_fin' => '18:00',
    ]);

    $other = Agent::query()->create([
        'grade_id' => $this->agent->grade_id,
        'type' => TypeAgent::Agent,
        'nom' => 'Open',
        'prenom' => 'Ended',
        'matricule' => 'AG-2005',
        'statut' => 'disponible',
    ]);

    $this->actingAs($this->admin)->postJson('/api/v1/vacations', [
        'agent_id' => $this->agent->id,
        'site_id' => $this->site->id,
        'poste_id' => $poste->id,
        'date_debut' => '2026-07-20',
        'date_fin' => null,
        'heure_debut' => '08:00',
        'heure_fin' => '18:00',
    ])->assertCreated();

    $this->actingAs($this->admin)->postJson('/api/v1/vacations', [
        'agent_id' => $other->id,
        'site_id' => $this->site->id,
        'poste_id' => $poste->id,
        'date_debut' => '2026-07-22',
        'date_fin' => '2026-07-22',
        'heure_debut' => '08:00',
        'heure_fin' => '18:00',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['poste_id']);
});

it('rejects a vacation with insufficient rest before another vacation of the same agent', function () {
    // Explicit same-day date_fin: a single-day shift, not an open-ended
    // one (a null date_fin would make it "ongoing" and conflict outright).
    $this->actingAs($this->admin)->postJson('/api/v1/vacations', [
        'agent_id' => $this->agent->id,
        'site_id' => $this->site->id,
        'date_debut' => '2026-07-20',
        'date_fin' => '2026-07-20',
        'heure_debut' => '08:00',
        'heure_fin' => '16:00',
    ])->assertCreated();

    // Only 1h gap after the first shift ends (16:00 -> 17:00): below the
    // 11h default minimum rest.
    $this->actingAs($this->admin)->postJson('/api/v1/vacations', [
        'agent_id' => $this->agent->id,
        'site_id' => $this->site->id,
        'date_debut' => '2026-07-20',
        'date_fin' => '2026-07-20',
        'heure_debut' => '17:00',
        'heure_fin' => '20:00',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['heure_debut']);
});

it('rejects night then day with 0h rest even on the same poste', function () {
    $poste = \App\Models\Poste::query()->create([
        'site_id' => $this->site->id,
        'nom' => 'Entrée quarts',
        'agents_requis' => 2,
        'heure_debut' => '06:30',
        'heure_fin' => '18:30',
        'heure_debut_nuit' => '18:30',
        'heure_fin_nuit' => '06:30',
    ]);

    $this->actingAs($this->admin)->postJson('/api/v1/vacations', [
        'agent_id' => $this->agent->id,
        'site_id' => $this->site->id,
        'poste_id' => $poste->id,
        'date_debut' => '2026-08-02',
        'date_fin' => '2026-08-03',
        'heure_debut' => '18:30',
        'heure_fin' => '06:30',
    ])->assertCreated();

    // Fin de nuit 03/08 06:30 → jour 03/08 06:30 = 0h de repos → refusé.
    $this->actingAs($this->admin)->postJson('/api/v1/vacations', [
        'agent_id' => $this->agent->id,
        'site_id' => $this->site->id,
        'poste_id' => $poste->id,
        'date_debut' => '2026-08-03',
        'date_fin' => '2026-08-03',
        'heure_debut' => '06:30',
        'heure_fin' => '18:30',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['heure_debut']);
});

it('allows consecutive cycle-24h days on the same poste (relève continue)', function () {
    $poste = \App\Models\Poste::query()->create([
        'site_id' => $this->site->id,
        'nom' => 'Portail cycle',
        'agents_requis' => 1,
        'heure_debut' => '06:30',
        'heure_fin' => '06:30',
    ]);

    $this->actingAs($this->admin)->postJson('/api/v1/vacations', [
        'agent_id' => $this->agent->id,
        'site_id' => $this->site->id,
        'poste_id' => $poste->id,
        'date_debut' => '2026-07-24',
        'date_fin' => '2026-07-24',
        'heure_debut' => '06:30',
        'heure_fin' => '06:30',
    ])->assertCreated();

    // Jour suivant : fin J = début J+1 (0h gap) — autorisé sur le même poste.
    $this->actingAs($this->admin)->postJson('/api/v1/vacations', [
        'agent_id' => $this->agent->id,
        'site_id' => $this->site->id,
        'poste_id' => $poste->id,
        'date_debut' => '2026-07-25',
        'date_fin' => '2026-07-25',
        'heure_debut' => '06:30',
        'heure_fin' => '06:30',
    ])->assertCreated();
});
