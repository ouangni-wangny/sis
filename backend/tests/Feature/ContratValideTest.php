<?php

use App\Domain\Shared\Enums\TypeAgent;
use App\Models\Agent;
use App\Models\Contrat;
use App\Models\Grade;
use App\Models\Client;
use App\Models\User;
use App\Models\Zone;
use App\Models\Vacation;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->admin = User::factory()->create([
        'email' => 'contrat-valide@sis.ci',
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
        'nom' => 'Diallo',
        'prenom' => 'Moussa',
        'matricule' => 'CV-001',
        'statut' => 'disponible',
    ]);

    // Pas de contrat par défaut : les scénarios de ce fichier le créent explicitement.
    Contrat::query()->where('agent_id', $this->agent->id)->delete();
});

function createContrat(Agent $agent, array $overrides = []): Contrat
{
    return Contrat::query()->create(array_merge([
        'agent_id' => $agent->id,
        'type' => 'cdi',
        'date_debut' => '2026-01-01',
        'statut' => 'actif',
    ], $overrides));
}

it('exclut les agents sans contrat valide quand contrat_valide=1', function () {
    $avecContrat = Agent::query()->create([
        'grade_id' => $this->agent->grade_id,
        'type' => TypeAgent::Agent,
        'nom' => 'Traore',
        'prenom' => 'Aminata',
        'matricule' => 'CV-002',
        'statut' => 'disponible',
    ]);
    // Le hook de test crée déjà un CDI actif pour $avecContrat.

    $response = $this->actingAs($this->admin)->getJson('/api/v1/agents?contrat_valide=1&type=agent');

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id');
    expect($ids)->toContain($avecContrat->id)
        ->and($ids)->not->toContain($this->agent->id);
});

it('inclut tous les agents sans filtre contrat_valide', function () {
    createContrat($this->agent);

    $response = $this->actingAs($this->admin)->getJson('/api/v1/agents?type=agent');

    $response->assertOk();
    expect(collect($response->json('data'))->pluck('id'))->toContain($this->agent->id);
});

it('exclut un agent dont le contrat est terminé', function () {
    createContrat($this->agent, ['statut' => 'termine', 'date_fin' => '2025-12-31']);

    $response = $this->actingAs($this->admin)->getJson('/api/v1/agents?contrat_valide=1&type=agent');

    expect(collect($response->json('data'))->pluck('id'))->not->toContain($this->agent->id);
});

it('exclut un agent dont le contrat CDD est expiré mais encore actif', function () {
    createContrat($this->agent, [
        'type' => 'cdd',
        'date_fin' => now()->subDay()->toDateString(),
        'statut' => 'actif',
    ]);

    $response = $this->actingAs($this->admin)->getJson('/api/v1/agents?contrat_valide=1&type=agent');

    expect(collect($response->json('data'))->pluck('id'))->not->toContain($this->agent->id);
});

function createTestSite(): \App\Models\Site
{
    $client = Client::query()->create([
        'type' => 'entreprise',
        'raison_sociale' => 'Client test contrat',
        'statut' => 'actif',
    ]);
    $zone = Zone::query()->create(['nom' => 'Zone test contrat']);

    return \App\Models\Site::query()->create([
        'client_id' => $client->id,
        'zone_id' => $zone->id,
        'nom' => 'Site test contrat',
        'adresse' => 'Abidjan',
    ]);
}

it('refuse de planifier une vacation pour un agent sans contrat valide', function () {
    $site = createTestSite();

    $this->actingAs($this->admin)->postJson('/api/v1/vacations', [
        'agent_id' => $this->agent->id,
        'site_id' => $site->id,
        'date_debut' => now()->addDay()->toDateString(),
        'heure_debut' => '08:00',
        'heure_fin' => '18:00',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['agent_id']);
});

it('archive l’agent et marque les vacations à recouvrir à la clôture du contrat', function () {
    $contrat = createContrat($this->agent);

    $site = createTestSite();

    $vacation = Vacation::query()->create([
        'agent_id' => $this->agent->id,
        'site_id' => $site->id,
        'date_debut' => now()->addDay()->toDateString(),
        'heure_debut' => '08:00',
        'heure_fin' => '18:00',
        'statut' => 'planifiee',
    ]);

    $this->actingAs($this->admin)->patchJson("/api/v1/contrats/{$contrat->id}", [
        'statut' => 'termine',
        'date_fin' => now()->toDateString(),
    ])->assertOk();

    expect($this->agent->fresh()->statut->value)->toBe('archive')
        ->and($vacation->fresh()->statut->value)->toBe('a_recouvrir');
});

it('clôt automatiquement les contrats expirés via le job', function () {
    createContrat($this->agent, [
        'type' => 'cdd',
        'date_fin' => now()->subDays(3)->toDateString(),
        'statut' => 'actif',
    ]);

    (new \App\Jobs\CloturerContratsExpiresJob)->handle(app(\App\Application\Contrat\HandleContratClotureAction::class));

    expect(Contrat::query()->where('agent_id', $this->agent->id)->value('statut'))->toBe('termine')
        ->and($this->agent->fresh()->statut->value)->toBe('archive');
});

it('redevient disponible quand un contrat actif est créé pour un agent archivé', function () {
    $this->agent->update(['statut' => 'archive']);

    $this->actingAs($this->admin)->postJson('/api/v1/contrats', [
        'agent_id' => $this->agent->id,
        'type' => 'cdi',
        'date_debut' => '2026-01-01',
        'statut' => 'actif',
    ])->assertSuccessful();

    expect($this->agent->fresh()->statut->value)->toBe('disponible');
});

it('redevient disponible quand un contrat est réactivé', function () {
    $contrat = createContrat($this->agent, ['statut' => 'termine', 'date_fin' => '2025-12-31']);
    $this->agent->update(['statut' => 'archive']);

    $this->actingAs($this->admin)->patchJson("/api/v1/contrats/{$contrat->id}", [
        'statut' => 'actif',
        'date_fin' => null,
    ])->assertOk();

    expect($this->agent->fresh()->statut->value)->toBe('disponible');
});

it('filtre les contrats valides sur l’index (hors terminés, résiliés et expirés)', function () {
    $gradeId = $this->agent->grade_id;

    $cdiActif = createContrat($this->agent);

    $stagiaireEnCours = Agent::query()->create([
        'grade_id' => $gradeId,
        'type' => TypeAgent::Agent,
        'nom' => 'Yao',
        'prenom' => 'Awa',
        'matricule' => 'CV-STAGE-OK',
        'statut' => 'disponible',
    ]);
    $stageEnCours = createContrat($stagiaireEnCours, [
        'type' => 'stage',
        'date_fin' => now()->addMonths(2)->toDateString(),
    ]);

    $stagiaireExpire = Agent::query()->create([
        'grade_id' => $gradeId,
        'type' => TypeAgent::Agent,
        'nom' => 'Kone',
        'prenom' => 'Ibrahim',
        'matricule' => 'CV-STAGE-EXP',
        'statut' => 'disponible',
    ]);
    $stageExpire = createContrat($stagiaireExpire, [
        'type' => 'stage',
        'date_fin' => now()->subDay()->toDateString(),
        'statut' => 'actif',
    ]);

    $stagiaireTermine = Agent::query()->create([
        'grade_id' => $gradeId,
        'type' => TypeAgent::Agent,
        'nom' => 'Bamba',
        'prenom' => 'Fatou',
        'matricule' => 'CV-STAGE-END',
        'statut' => 'archive',
    ]);
    $stageTermine = createContrat($stagiaireTermine, [
        'type' => 'stage',
        'date_fin' => now()->subDays(10)->toDateString(),
        'statut' => 'termine',
    ]);

    $stagiaireResilie = Agent::query()->create([
        'grade_id' => $gradeId,
        'type' => TypeAgent::Agent,
        'nom' => 'Traore',
        'prenom' => 'Salif',
        'matricule' => 'CV-STAGE-RES',
        'statut' => 'archive',
    ]);
    $stageResilie = createContrat($stagiaireResilie, [
        'type' => 'stage',
        'date_fin' => now()->subDays(5)->toDateString(),
        'statut' => 'resilie',
    ]);

    $tous = $this->actingAs($this->admin)->getJson('/api/v1/contrats?type=stage&per_page=50');
    $tous->assertOk();
    expect($tous->json('meta.total'))->toBe(4);

    $valides = $this->actingAs($this->admin)->getJson('/api/v1/contrats?type=stage&valide=1&per_page=50');
    $valides->assertOk();
    $ids = collect($valides->json('data'))->pluck('id');

    expect($valides->json('meta.total'))->toBe(1)
        ->and($ids)->toContain($stageEnCours->id)
        ->and($ids)->not->toContain($stageExpire->id)
        ->and($ids)->not->toContain($stageTermine->id)
        ->and($ids)->not->toContain($stageResilie->id)
        ->and($ids)->not->toContain($cdiActif->id);
});
