<?php

use App\Application\Operation\GenererVacationsPoolSiegeAction;
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
        'email' => 'admin-pool-siege@sis.ci',
        'password' => Hash::make('password'),
    ]);
    $this->admin->assignRole('super-admin');

    $grade = Grade::query()->create([
        'libelle' => 'Agent Pool Siège',
        'type_agent' => TypeAgent::Agent,
    ]);

    $client = Client::query()->create([
        'type' => 'entreprise',
        'raison_sociale' => 'Client Pool Siège',
        'statut' => 'actif',
    ]);
    $zone = Zone::query()->create(['nom' => 'Zone Pool Siège']);

    $this->siteSiege = Site::query()->create([
        'client_id' => $client->id,
        'zone_id' => $zone->id,
        'nom' => 'Siège',
        'interne' => true,
    ]);
    $this->posteSiege = Poste::query()->create([
        'site_id' => $this->siteSiege->id,
        'nom' => 'Accueil siège',
        'agents_requis' => 5,
        'heure_debut' => '08:00',
        'heure_fin' => '17:00',
    ]);

    $this->siteClient = Site::query()->create([
        'client_id' => $client->id,
        'zone_id' => $zone->id,
        'nom' => 'Site client',
    ]);
    $this->posteClient = Poste::query()->create([
        'site_id' => $this->siteClient->id,
        'nom' => 'Poste entrée',
        'agents_requis' => 1,
        'heure_debut' => '08:00',
        'heure_fin' => '16:00',
    ]);

    $this->agentPool = Agent::query()->create([
        'grade_id' => $grade->id,
        'type' => TypeAgent::Agent,
        'nom' => 'Pool',
        'prenom' => 'Agent',
        'matricule' => 'AG-POOL-001',
        'statut' => 'disponible',
        'pool_siege' => true,
        'poste_siege_id' => $this->posteSiege->id,
    ]);
});

it('génère une vacation du jour pour un agent du pool siège', function () {
    $result = app(GenererVacationsPoolSiegeAction::class)->execute('2026-09-01');

    expect($result['created'])->toBe(1);
    expect(Vacation::query()
        ->where('agent_id', $this->agentPool->id)
        ->where('poste_id', $this->posteSiege->id)
        ->whereDate('date_debut', '2026-09-01')
        ->where('statut', 'planifiee')
        ->exists())->toBeTrue();
});

it('est rejouable sans dupliquer la vacation du jour', function () {
    $action = app(GenererVacationsPoolSiegeAction::class);
    $action->execute('2026-09-01');
    $second = $action->execute('2026-09-01');

    expect($second['created'])->toBe(0);
    expect($second['skipped'])->toBe(1);
    expect(Vacation::query()
        ->where('agent_id', $this->agentPool->id)
        ->whereDate('date_debut', '2026-09-01')
        ->count())->toBe(1);
});

it('saute un agent du pool déjà engagé ailleurs ce jour-là sans planter le lot', function () {
    Vacation::query()->create([
        'agent_id' => $this->agentPool->id,
        'site_id' => $this->siteClient->id,
        'poste_id' => $this->posteClient->id,
        'date_debut' => '2026-09-01',
        'date_fin' => '2026-09-01',
        'heure_debut' => '08:00',
        'heure_fin' => '16:00',
        'statut' => 'planifiee',
    ]);

    $result = app(GenererVacationsPoolSiegeAction::class)->execute('2026-09-01');

    expect($result['created'])->toBe(0);
    expect($result['skipped'])->toBe(1);
});

it('libère automatiquement la vacation siège du remplaçant lors d’un recouvrement', function () {
    app(GenererVacationsPoolSiegeAction::class)->execute('2026-09-05');
    $vacationSiege = Vacation::query()
        ->where('agent_id', $this->agentPool->id)
        ->whereDate('date_debut', '2026-09-05')
        ->firstOrFail();

    $grade = Grade::query()->where('type_agent', TypeAgent::Agent->value)->first();
    $agentAbsent = Agent::query()->create([
        'grade_id' => $grade->id,
        'type' => TypeAgent::Agent,
        'nom' => 'Absent',
        'prenom' => 'Agent',
        'matricule' => 'AG-POOL-002',
        'statut' => 'disponible',
    ]);
    $trou = Vacation::query()->create([
        'agent_id' => $agentAbsent->id,
        'site_id' => $this->siteClient->id,
        'poste_id' => $this->posteClient->id,
        'date_debut' => '2026-09-05',
        'date_fin' => '2026-09-05',
        'heure_debut' => '08:00',
        'heure_fin' => '16:00',
        'statut' => 'a_recouvrir',
    ]);

    $response = $this->actingAs($this->admin)
        ->postJson('/api/v1/vacations/'.$trou->id.'/recouvrir', [
            'agent_id' => $this->agentPool->id,
        ])
        ->assertCreated();

    expect($response->json('data.agent_id'))->toBe($this->agentPool->id);
    expect($response->json('data.site_id'))->toBe($this->siteClient->id);
    expect($vacationSiege->fresh()->statut->value)->toBe('annulee');
    expect($trou->fresh()->statut->value)->toBe('annulee');
});

it('refuse de marquer un agent pool siège sans poste siège associé', function () {
    $grade = Grade::query()->where('type_agent', TypeAgent::Agent->value)->first();

    $this->actingAs($this->admin)->postJson('/api/v1/agents', [
        'grade_id' => $grade->id,
        'nom' => 'SansPoste',
        'prenom' => 'Agent',
        'pool_siege' => true,
    ])->assertStatus(422)
        ->assertJsonValidationErrors('poste_siege_id');
});

it('refuse un poste siège qui n’appartient pas à un site interne', function () {
    $grade = Grade::query()->where('type_agent', TypeAgent::Agent->value)->first();

    $this->actingAs($this->admin)->postJson('/api/v1/agents', [
        'grade_id' => $grade->id,
        'nom' => 'MauvaisSite',
        'prenom' => 'Agent',
        'pool_siege' => true,
        'poste_siege_id' => $this->posteClient->id,
    ])->assertStatus(422)
        ->assertJsonValidationErrors('poste_siege_id');
});
