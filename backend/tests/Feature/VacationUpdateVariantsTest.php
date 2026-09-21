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
        'email' => 'admin-vac-update@sis.ci',
        'password' => Hash::make('password'),
    ]);
    $this->admin->assignRole('super-admin');

    $grade = Grade::query()->create([
        'libelle' => 'Agent Upd',
        'type_agent' => TypeAgent::Agent,
    ]);

    $this->agentA = Agent::query()->create([
        'grade_id' => $grade->id,
        'type' => TypeAgent::Agent,
        'nom' => 'Alpha',
        'prenom' => 'Agent',
        'matricule' => 'AG-UPD-A',
        'statut' => 'disponible',
    ]);
    $this->agentB = Agent::query()->create([
        'grade_id' => $grade->id,
        'type' => TypeAgent::Agent,
        'nom' => 'Beta',
        'prenom' => 'Agent',
        'matricule' => 'AG-UPD-B',
        'statut' => 'disponible',
    ]);
    $this->agentC = Agent::query()->create([
        'grade_id' => $grade->id,
        'type' => TypeAgent::Agent,
        'nom' => 'Gamma',
        'prenom' => 'Agent',
        'matricule' => 'AG-UPD-C',
        'statut' => 'disponible',
    ]);

    ensureContratActif($this->agentA);
    ensureContratActif($this->agentB);
    ensureContratActif($this->agentC);

    $client = Client::query()->create([
        'type' => 'entreprise',
        'raison_sociale' => 'Client Upd',
        'statut' => 'actif',
    ]);
    $zone = Zone::query()->create(['nom' => 'Zone Upd']);

    $this->site1 = Site::query()->create([
        'client_id' => $client->id,
        'zone_id' => $zone->id,
        'nom' => 'Site Un',
    ]);
    $this->site2 = Site::query()->create([
        'client_id' => $client->id,
        'zone_id' => $zone->id,
        'nom' => 'Site Deux',
    ]);

    $this->poste1 = Poste::query()->create([
        'site_id' => $this->site1->id,
        'nom' => 'Poste Site1',
        'agents_requis' => 2,
        'heure_debut' => '08:00',
        'heure_fin' => '16:00',
    ]);
    $this->poste2 = Poste::query()->create([
        'site_id' => $this->site2->id,
        'nom' => 'Poste Site2',
        'agents_requis' => 2,
        'heure_debut' => '08:00',
        'heure_fin' => '16:00',
    ]);
});

function createVac($test, array $overrides = []): string
{
    $payload = array_merge([
        'agent_id' => $test->agentA->id,
        'site_id' => $test->site1->id,
        'poste_id' => $test->poste1->id,
        'date_debut' => '2026-09-01',
        'date_fin' => '2026-09-01',
        'heure_debut' => '08:00',
        'heure_fin' => '16:00',
    ], $overrides);

    $id = $test->actingAs($test->admin)
        ->postJson('/api/v1/vacations', $payload)
        ->assertCreated()
        ->json('data.id');

    return $id;
}

// ─── Mises à jour valides (toutes les variantes de champs) ───

it('updates agent only when the new agent is free', function () {
    $id = createVac($this);

    $this->actingAs($this->admin)
        ->putJson("/api/v1/vacations/{$id}", ['agent_id' => $this->agentB->id])
        ->assertOk()
        ->assertJsonPath('data.agent_id', $this->agentB->id);
});

it('updates site and poste together when poste belongs to site', function () {
    $id = createVac($this);

    $this->actingAs($this->admin)
        ->putJson("/api/v1/vacations/{$id}", [
            'site_id' => $this->site2->id,
            'poste_id' => $this->poste2->id,
        ])
        ->assertOk()
        ->assertJsonPath('data.site_id', $this->site2->id)
        ->assertJsonPath('data.poste_id', $this->poste2->id);
});

it('rejects poste that does not belong to the target site on update', function () {
    $id = createVac($this);

    $this->actingAs($this->admin)
        ->putJson("/api/v1/vacations/{$id}", [
            'site_id' => $this->site2->id,
            'poste_id' => $this->poste1->id, // poste du site 1
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['poste_id']);
});

it('updates hours within legal duration', function () {
    $id = createVac($this);

    $this->actingAs($this->admin)
        ->putJson("/api/v1/vacations/{$id}", [
            'heure_debut' => '07:00',
            'heure_fin' => '15:00',
        ])
        ->assertOk();

    $updated = Vacation::query()->findOrFail($id);
    expect(substr((string) $updated->heure_debut, 0, 5))->toBe('07:00');
    expect(substr((string) $updated->heure_fin, 0, 5))->toBe('15:00');
});

it('updates dates of a vacation without conflict', function () {
    $id = createVac($this);

    $this->actingAs($this->admin)
        ->putJson("/api/v1/vacations/{$id}", [
            'date_debut' => '2026-09-05',
            'date_fin' => '2026-09-05',
        ])
        ->assertOk();

    expect(Vacation::query()->findOrFail($id)->date_debut->format('Y-m-d'))->toBe('2026-09-05');
});

it('auto-sets date_fin when updating to overnight hours', function () {
    $id = createVac($this);

    $this->actingAs($this->admin)
        ->putJson("/api/v1/vacations/{$id}", [
            'heure_debut' => '19:00',
            'heure_fin' => '07:00',
            'date_fin' => null,
        ])
        ->assertOk();

    $vac = Vacation::query()->findOrFail($id);
    expect($vac->date_fin?->format('Y-m-d'))->toBe('2026-09-02');
});

it('marks vacation a_recouvrir via update', function () {
    $id = createVac($this);

    $this->actingAs($this->admin)
        ->putJson("/api/v1/vacations/{$id}", ['statut' => 'a_recouvrir'])
        ->assertOk()
        ->assertJsonPath('data.statut', 'a_recouvrir');
});

it('cancels vacation via update statut annulee', function () {
    $id = createVac($this);

    $this->actingAs($this->admin)
        ->putJson("/api/v1/vacations/{$id}", ['statut' => 'annulee'])
        ->assertOk()
        ->assertJsonPath('data.statut', 'annulee');
});

it('restores a_recouvrir to planifiee when the slot is free', function () {
    $id = createVac($this);
    $this->actingAs($this->admin)
        ->putJson("/api/v1/vacations/{$id}", ['statut' => 'a_recouvrir'])
        ->assertOk();

    $this->actingAs($this->admin)
        ->putJson("/api/v1/vacations/{$id}", ['statut' => 'planifiee'])
        ->assertOk()
        ->assertJsonPath('data.statut', 'planifiee');
});

it('allows full payload update (agent+site+poste+dates+hours)', function () {
    $id = createVac($this);

    $this->actingAs($this->admin)
        ->putJson("/api/v1/vacations/{$id}", [
            'agent_id' => $this->agentB->id,
            'site_id' => $this->site2->id,
            'poste_id' => $this->poste2->id,
            'date_debut' => '2026-09-10',
            'date_fin' => '2026-09-10',
            'heure_debut' => '09:00',
            'heure_fin' => '17:00',
        ])
        ->assertOk()
        ->assertJsonPath('data.agent_id', $this->agentB->id)
        ->assertJsonPath('data.site_id', $this->site2->id)
        ->assertJsonPath('data.poste_id', $this->poste2->id);

    expect(Vacation::query()->findOrFail($id)->date_debut->format('Y-m-d'))->toBe('2026-09-10');
});

// ─── Conflits agent (multi-site, chevauchement, update) ───

it('rejects creating the same agent on two different sites at the same time', function () {
    createVac($this, [
        'agent_id' => $this->agentA->id,
        'site_id' => $this->site1->id,
        'poste_id' => $this->poste1->id,
    ]);

    $this->actingAs($this->admin)->postJson('/api/v1/vacations', [
        'agent_id' => $this->agentA->id,
        'site_id' => $this->site2->id,
        'poste_id' => $this->poste2->id,
        'date_debut' => '2026-09-01',
        'date_fin' => '2026-09-01',
        'heure_debut' => '10:00',
        'heure_fin' => '14:00',
    ])->assertStatus(409)
        ->assertJsonPath('error', 'VacationConflitException');
});

it('rejects updating a vacation onto another site when agent is already busy elsewhere', function () {
    // Agent A sur site 1
    createVac($this, [
        'agent_id' => $this->agentA->id,
        'site_id' => $this->site1->id,
        'poste_id' => $this->poste1->id,
        'heure_debut' => '08:00',
        'heure_fin' => '16:00',
    ]);

    // Agent B sur site 2, même créneau
    $idB = createVac($this, [
        'agent_id' => $this->agentB->id,
        'site_id' => $this->site2->id,
        'poste_id' => $this->poste2->id,
        'heure_debut' => '08:00',
        'heure_fin' => '16:00',
    ]);

    // Transférer la vacation B à l’agent A → conflit multi-site
    $this->actingAs($this->admin)
        ->putJson("/api/v1/vacations/{$idB}", ['agent_id' => $this->agentA->id])
        ->assertStatus(409);
});

it('rejects updating hours to overlap another vacation of the same agent', function () {
    $id1 = createVac($this, [
        'date_debut' => '2026-09-01',
        'date_fin' => '2026-09-01',
        'heure_debut' => '08:00',
        'heure_fin' => '12:00',
    ]);

    createVac($this, [
        'agent_id' => $this->agentA->id,
        'date_debut' => '2026-09-02',
        'date_fin' => '2026-09-02',
        'heure_debut' => '08:00',
        'heure_fin' => '16:00',
    ]);

    // Décaler id1 sur le 2 septembre → chevauchement
    $this->actingAs($this->admin)
        ->putJson("/api/v1/vacations/{$id1}", [
            'date_debut' => '2026-09-02',
            'date_fin' => '2026-09-02',
            'heure_debut' => '10:00',
            'heure_fin' => '18:00',
        ])
        ->assertStatus(409);
});

it('allows updating a vacation without conflicting with itself', function () {
    $id = createVac($this);

    $this->actingAs($this->admin)
        ->putJson("/api/v1/vacations/{$id}", [
            'heure_debut' => '08:30',
            'heure_fin' => '16:30',
        ])
        ->assertOk();
});

it('allows adjacent non-overlapping shifts after sufficient rest on update', function () {
    createVac($this, [
        'date_debut' => '2026-09-01',
        'date_fin' => '2026-09-01',
        'heure_debut' => '08:00',
        'heure_fin' => '16:00',
    ]);

    $id2 = createVac($this, [
        'agent_id' => $this->agentA->id,
        'date_debut' => '2026-09-03',
        'date_fin' => '2026-09-03',
        'heure_debut' => '08:00',
        'heure_fin' => '16:00',
    ]);

    // Rapprocher au 2 sept 08:00 = 16h de repos après 16:00 veille → OK (≥ 11h)
    $this->actingAs($this->admin)
        ->putJson("/api/v1/vacations/{$id2}", [
            'date_debut' => '2026-09-02',
            'date_fin' => '2026-09-02',
        ])
        ->assertOk();
});

it('rejects update with insufficient rest after another vacation', function () {
    createVac($this, [
        'date_debut' => '2026-09-01',
        'date_fin' => '2026-09-01',
        'heure_debut' => '08:00',
        'heure_fin' => '16:00',
    ]);

    $id2 = createVac($this, [
        'agent_id' => $this->agentA->id,
        'date_debut' => '2026-09-03',
        'date_fin' => '2026-09-03',
        'heure_debut' => '08:00',
        'heure_fin' => '12:00',
    ]);

    // 17:00 le même jour = 1h de repos seulement
    $this->actingAs($this->admin)
        ->putJson("/api/v1/vacations/{$id2}", [
            'date_debut' => '2026-09-01',
            'date_fin' => '2026-09-01',
            'heure_debut' => '17:00',
            'heure_fin' => '20:00',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['heure_debut']);
});

// ─── Capacité poste / statut agent ───

it('rejects update that would overfill a poste', function () {
    $posteCap1 = Poste::query()->create([
        'site_id' => $this->site1->id,
        'nom' => 'Cap1',
        'agents_requis' => 1,
        'heure_debut' => '08:00',
        'heure_fin' => '16:00',
    ]);

    createVac($this, [
        'agent_id' => $this->agentA->id,
        'poste_id' => $posteCap1->id,
    ]);

    $idB = createVac($this, [
        'agent_id' => $this->agentB->id,
        'date_debut' => '2026-09-02',
        'date_fin' => '2026-09-02',
        'poste_id' => $this->poste1->id,
    ]);

    // Déplacer B sur le poste déjà plein le 1er sept
    $this->actingAs($this->admin)
        ->putJson("/api/v1/vacations/{$idB}", [
            'date_debut' => '2026-09-01',
            'date_fin' => '2026-09-01',
            'poste_id' => $posteCap1->id,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['poste_id']);
});

it('rejects assigning an unavailable agent on update', function () {
    $id = createVac($this, ['agent_id' => $this->agentA->id]);

    $this->agentB->update(['statut' => 'malade']);

    $this->actingAs($this->admin)
        ->putJson("/api/v1/vacations/{$id}", ['agent_id' => $this->agentB->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['agent_id']);
});

it('allows marking a_recouvrir even if agent later becomes unavailable', function () {
    $id = createVac($this);
    $this->agentA->update(['statut' => 'malade']);

    $this->actingAs($this->admin)
        ->putJson("/api/v1/vacations/{$id}", ['statut' => 'a_recouvrir'])
        ->assertOk()
        ->assertJsonPath('data.statut', 'a_recouvrir');
});

it('rejects restoring planifiee when another active vacation occupies the agent', function () {
    $idHole = createVac($this, [
        'agent_id' => $this->agentA->id,
        'heure_debut' => '08:00',
        'heure_fin' => '16:00',
    ]);
    $this->actingAs($this->admin)
        ->putJson("/api/v1/vacations/{$idHole}", ['statut' => 'a_recouvrir'])
        ->assertOk();

    // Remplaçant créé via create pour le même créneau (trou exclu des conflits)
    createVac($this, [
        'agent_id' => $this->agentB->id,
        'heure_debut' => '08:00',
        'heure_fin' => '16:00',
    ]);

    // Autre vacation active de A le même jour ailleurs → si on restaure le trou A, conflit avec... wait
    // A is only on the hole. Create another active for A on same slot different site:
    createVac($this, [
        'agent_id' => $this->agentA->id,
        'site_id' => $this->site2->id,
        'poste_id' => $this->poste2->id,
        'heure_debut' => '08:00',
        'heure_fin' => '16:00',
    ]);

    $this->actingAs($this->admin)
        ->putJson("/api/v1/vacations/{$idHole}", ['statut' => 'planifiee'])
        ->assertStatus(409);
});

it('rejects date_fin before date_debut on update', function () {
    $id = createVac($this);

    $this->actingAs($this->admin)
        ->putJson("/api/v1/vacations/{$id}", [
            'date_debut' => '2026-09-10',
            'date_fin' => '2026-09-09',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['date_fin']);
});

it('rejects update exceeding max shift duration', function () {
    $id = createVac($this);

    $this->actingAs($this->admin)
        ->putJson("/api/v1/vacations/{$id}", [
            'heure_debut' => '06:00',
            'heure_fin' => '20:00',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['heure_fin']);
});

it('frees the slot after cancel so another agent can take it', function () {
    $posteCap1 = Poste::query()->create([
        'site_id' => $this->site1->id,
        'nom' => 'Seul',
        'agents_requis' => 1,
        'heure_debut' => '08:00',
        'heure_fin' => '16:00',
    ]);

    $id = createVac($this, [
        'agent_id' => $this->agentA->id,
        'poste_id' => $posteCap1->id,
    ]);

    $this->actingAs($this->admin)
        ->putJson("/api/v1/vacations/{$id}", ['statut' => 'annulee'])
        ->assertOk();

    $this->actingAs($this->admin)->postJson('/api/v1/vacations', [
        'agent_id' => $this->agentB->id,
        'site_id' => $this->site1->id,
        'poste_id' => $posteCap1->id,
        'date_debut' => '2026-09-01',
        'date_fin' => '2026-09-01',
        'heure_debut' => '08:00',
        'heure_fin' => '16:00',
    ])->assertCreated();
});

it('detects overnight update conflict with morning shift next day', function () {
    createVac($this, [
        'agent_id' => $this->agentA->id,
        'date_debut' => '2026-09-02',
        'date_fin' => '2026-09-02',
        'heure_debut' => '05:00',
        'heure_fin' => '09:00',
    ]);

    $id = createVac($this, [
        'agent_id' => $this->agentA->id,
        'date_debut' => '2026-09-01',
        'date_fin' => '2026-09-01',
        'heure_debut' => '08:00',
        'heure_fin' => '12:00',
    ]);

    // Passer en nuit 20→06 → chevauche le matin du 2
    $this->actingAs($this->admin)
        ->putJson("/api/v1/vacations/{$id}", [
            'heure_debut' => '20:00',
            'heure_fin' => '06:00',
            'date_fin' => null,
        ])
        ->assertStatus(409);
});
