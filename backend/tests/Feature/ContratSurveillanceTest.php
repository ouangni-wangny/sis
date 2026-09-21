<?php

use App\Domain\Shared\Enums\TypeAgent;
use App\Models\Agent;
use App\Models\Contrat;
use App\Models\Grade;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->admin = User::factory()->create([
        'email' => 'contrat-surveillance@sis.ci',
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
        'nom' => 'Kouassi',
        'prenom' => 'Jean',
        'matricule' => 'CS-001',
        'statut' => 'disponible',
    ]);

    Contrat::query()->where('agent_id', $this->agent->id)->delete();
});

function createSurveillanceContrat(Agent $agent, array $overrides = []): Contrat
{
    return Contrat::query()->create(array_merge([
        'agent_id' => $agent->id,
        'type' => 'cdd',
        'date_debut' => '2026-01-01',
        'statut' => 'actif',
    ], $overrides));
}

it('filtre les contrats à surveiller côté API', function () {
    Carbon::setTestNow('2026-08-28');

    $alerteFin = createSurveillanceContrat($this->agent, [
        'reference' => 'CS-FIN',
        'date_fin' => '2026-09-10',
    ]);

    $agentHorsAlerte = Agent::query()->create([
        'grade_id' => $this->agent->grade_id,
        'type' => TypeAgent::Agent,
        'nom' => 'Bamba',
        'prenom' => 'Ali',
        'matricule' => 'CS-002',
        'statut' => 'disponible',
    ]);
    Contrat::query()->where('agent_id', $agentHorsAlerte->id)->delete();

    $horsAlerte = createSurveillanceContrat($agentHorsAlerte, [
        'reference' => 'CS-OK',
        'date_fin' => '2027-01-01',
    ]);

    $response = $this->actingAs($this->admin)->getJson('/api/v1/contrats?surveillance=1&per_page=50');

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id');

    expect($ids)->toContain($alerteFin->id)
        ->and($ids)->not->toContain($horsAlerte->id)
        ->and($response->json('meta.total'))->toBe(1);

    Carbon::setTestNow();
});

it('aligne le filtre surveillance avec les alertes contrats', function () {
    Carbon::setTestNow('2026-08-28');

    createSurveillanceContrat($this->agent, [
        'reference' => 'CS-ESSAI',
        'periode_essai_mois' => 3,
        'date_debut' => '2026-06-01',
    ]);

    $surveillance = $this->actingAs($this->admin)->getJson('/api/v1/contrats?surveillance=1&per_page=50');
    $alerts = $this->actingAs($this->admin)->getJson('/api/v1/contrats/alerts');

    $surveillance->assertOk();
    $alerts->assertOk();

    expect($surveillance->json('meta.total'))->toBeGreaterThan(0)
        ->and(collect($alerts->json('data'))->pluck('contrat_id'))->toContain($this->agent->contrats()->first()->id);

    Carbon::setTestNow();
});
