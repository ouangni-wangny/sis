<?php

use App\Application\Contrat\HandleContratClotureAction;
use App\Domain\Shared\Enums\StatutAgent;
use App\Domain\Shared\Enums\TypeAgent;
use App\Jobs\CloturerContratsExpiresJob;
use App\Models\Agent;
use App\Models\Client;
use App\Models\Contrat;
use App\Models\Grade;
use App\Models\RondierPerimetre;
use App\Models\Site;
use App\Models\User;
use App\Models\Vacation;
use App\Models\Zone;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    Carbon::setTestNow('2026-11-02 10:00:00');

    $this->seed(RolePermissionSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('super-admin');

    $grade = Grade::query()->create([
        'libelle' => 'Agent',
        'type_agent' => TypeAgent::Agent,
    ]);

    $this->agent = Agent::query()->create([
        'grade_id' => $grade->id,
        'type' => TypeAgent::Agent,
        'nom' => 'Konan',
        'prenom' => 'Evariste',
        'matricule' => 'AG-4001',
        'statut' => 'disponible',
    ]);

    Contrat::query()->where('agent_id', $this->agent->id)->forceDelete();

    $this->contrat = Contrat::query()->create([
        'agent_id' => $this->agent->id,
        'type' => 'stage',
        'reference' => 'CTR-STG-2026-0359',
        'date_debut' => '2026-01-01',
        'date_fin' => '2026-12-31',
        'salaire_base' => 100000,
        'statut' => 'actif',
    ]);

    $this->renouveler = fn (array $payload = [], ?Contrat $source = null) => $this
        ->actingAs($this->admin)
        ->postJson('/api/v1/contrats/'.($source ?? $this->contrat)->id.'/renouveler', [
            'type' => 'cdd',
            'date_debut' => '2027-01-01',
            'date_fin' => '2027-12-31',
            'salaire_base' => 250000,
            'prime_transport' => 30000,
            ...$payload,
        ]);
});

afterEach(function () {
    Carbon::setTestNow();
});

/** Met l’agent en poste : statut, périmètre et vacation à venir au-delà de la fin du contrat. */
function affecterAgent(object $test): Vacation
{
    $test->agent->update(['statut' => StatutAgent::EnActivite]);

    $client = Client::query()->create([
        'type' => 'entreprise',
        'raison_sociale' => 'Client Renouvellement',
        'statut' => 'actif',
    ]);
    $zone = Zone::query()->create(['nom' => 'Zone Renouvellement']);
    $site = Site::query()->create([
        'client_id' => $client->id,
        'zone_id' => $zone->id,
        'nom' => 'Site Renouvellement',
    ]);

    RondierPerimetre::query()->create(['agent_id' => $test->agent->id, 'zone_id' => $zone->id]);

    return Vacation::query()->create([
        'agent_id' => $test->agent->id,
        'site_id' => $site->id,
        'date_debut' => '2027-01-15',
        'heure_debut' => '08:00',
        'heure_fin' => '16:00',
        'statut' => 'planifiee',
    ]);
}

it('crée le nouveau contrat aux nouveaux termes sans recréer l’agent', function () {
    $agentsAvant = Agent::query()->count();

    $response = ($this->renouveler)()
        ->assertCreated()
        ->assertJsonPath('data.agent_id', $this->agent->id)
        ->assertJsonPath('data.contrat_parent_id', $this->contrat->id)
        ->assertJsonPath('data.contrat_parent.reference', 'CTR-STG-2026-0359')
        ->assertJsonPath('data.type', 'cdd')
        ->assertJsonPath('data.statut', 'actif')
        ->assertJsonPath('data.date_debut', '2027-01-01')
        ->assertJsonPath('data.date_fin', '2027-12-31')
        ->assertJsonPath('data.reference', 'CTR-CDD-2027-0001');

    expect((float) $response->json('data.salaire_brut'))->toBe(280000.0)
        ->and(Agent::query()->count())->toBe($agentsAvant)
        ->and(Contrat::query()->where('agent_id', $this->agent->id)->count())->toBe(2);
});

it('garde l’ancien contrat actif jusqu’à sa fin si le renouvellement est anticipé', function () {
    ($this->renouveler)()->assertCreated();

    $source = $this->contrat->fresh();

    expect($source->statut)->toBe('actif')
        ->and($source->date_fin->toDateString())->toBe('2026-12-31');
});

it('ne touche ni au statut, ni aux périmètres, ni aux vacations de l’agent en poste', function () {
    $vacation = affecterAgent($this);

    ($this->renouveler)(['date_debut' => '2026-11-02', 'date_fin' => '2027-10-31'])->assertCreated();

    expect($this->agent->fresh()->statut)->toBe(StatutAgent::EnActivite)
        ->and($this->agent->perimetres()->count())->toBe(1)
        ->and($vacation->fresh()->statut->value)->toBe('planifiee');
});

it('raccourcit l’ancien contrat quand le renouvellement démarre avant sa fin', function () {
    ($this->renouveler)(['date_debut' => '2026-12-01', 'date_fin' => '2027-11-30'])->assertCreated();

    expect($this->contrat->fresh()->date_fin->toDateString())->toBe('2026-11-30')
        ->and($this->contrat->fresh()->statut)->toBe('actif');
});

it('termine l’ancien contrat quand le renouvellement démarre aujourd’hui ou avant', function () {
    ($this->renouveler)(['date_debut' => '2026-11-02', 'date_fin' => '2027-10-31'])->assertCreated();

    $source = $this->contrat->fresh();

    expect($source->date_fin->toDateString())->toBe('2026-11-01')
        ->and($source->statut)->toBe('termine');
});

it('renouvelle un CDI actif sans date de fin en le clôturant la veille', function () {
    $this->contrat->update(['type' => 'cdi', 'date_fin' => null]);

    ($this->renouveler)(['type' => 'cdi', 'date_debut' => '2027-01-01', 'date_fin' => null])
        ->assertCreated()
        ->assertJsonPath('data.date_fin', null);

    expect($this->contrat->fresh()->date_fin->toDateString())->toBe('2026-12-31')
        ->and($this->contrat->fresh()->statut)->toBe('actif');
});

it('réactive l’agent archivé par la fin de son contrat, sans le recréer', function () {
    $this->contrat->update(['date_fin' => '2026-10-23', 'statut' => 'termine']);
    $this->agent->update(['statut' => StatutAgent::Archive]);

    ($this->renouveler)(['date_debut' => '2026-11-02', 'date_fin' => '2027-10-31'])->assertCreated();

    $agent = $this->agent->fresh();

    expect($agent->statut)->toBe(StatutAgent::Disponible)
        ->and($agent->matricule)->toBe('AG-4001');
});

it('laisse l’agent en poste quand le job clôture un contrat déjà renouvelé', function () {
    $vacation = affecterAgent($this);
    ($this->renouveler)()->assertCreated();

    Carbon::setTestNow('2027-01-01 00:05:00');
    (new CloturerContratsExpiresJob)->handle(app(HandleContratClotureAction::class));

    expect($this->contrat->fresh()->statut)->toBe('termine')
        ->and($this->agent->fresh()->statut)->toBe(StatutAgent::EnActivite)
        ->and($this->agent->perimetres()->count())->toBe(1)
        ->and($vacation->fresh()->statut->value)->toBe('planifiee');
});

it('retire toujours l’agent du terrain quand le contrat expire sans renouvellement', function () {
    $vacation = affecterAgent($this);

    Carbon::setTestNow('2027-01-01 00:05:00');
    (new CloturerContratsExpiresJob)->handle(app(HandleContratClotureAction::class));

    expect($this->contrat->fresh()->statut)->toBe('termine')
        ->and($this->agent->fresh()->statut)->toBe(StatutAgent::Archive)
        ->and($this->agent->perimetres()->count())->toBe(0)
        ->and($vacation->fresh()->statut->value)->toBe('a_recouvrir');
});

it('refuse de renouveler deux fois le même contrat', function () {
    ($this->renouveler)()->assertCreated();

    ($this->renouveler)(['date_debut' => '2028-01-01', 'date_fin' => '2028-12-31'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['contrat']);

    expect(Contrat::query()->where('agent_id', $this->agent->id)->count())->toBe(2);
});

it('permet de renouveler à nouveau si le renouvellement a été supprimé', function () {
    $id = ($this->renouveler)()->assertCreated()->json('data.id');
    Contrat::query()->findOrFail($id)->delete();

    ($this->renouveler)()->assertCreated();
});

it('refuse un chevauchement avec un autre contrat actif de l’agent', function () {
    Contrat::query()->create([
        'agent_id' => $this->agent->id,
        'type' => 'cdd',
        'date_debut' => '2027-06-01',
        'date_fin' => '2027-08-31',
        'statut' => 'actif',
    ]);

    ($this->renouveler)()
        ->assertStatus(422)
        ->assertJsonValidationErrors(['date_debut']);

    expect($this->contrat->fresh()->date_fin->toDateString())->toBe('2026-12-31');
});

it('refuse un début antérieur ou égal au début du contrat actuel', function () {
    ($this->renouveler)(['date_debut' => '2026-01-01', 'date_fin' => '2026-12-31'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['date_debut']);
});

it('applique les règles du nouveau type de contrat', function () {
    ($this->renouveler)(['date_fin' => null])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['date_fin']);

    ($this->renouveler)(['type' => 'stage', 'date_fin' => '2028-12-31'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['date_fin']);

    ($this->renouveler)(['periode_essai_mois' => 3])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['periode_essai_mois']);
});

it('ignore un statut ou un agent envoyés dans la requête', function () {
    $autre = Agent::query()->create([
        'grade_id' => $this->agent->grade_id,
        'type' => TypeAgent::Agent,
        'nom' => 'Autre',
        'prenom' => 'Agent',
        'matricule' => 'AG-4002',
        'statut' => 'disponible',
    ]);

    ($this->renouveler)(['statut' => 'resilie', 'agent_id' => $autre->id])
        ->assertCreated()
        ->assertJsonPath('data.statut', 'actif')
        ->assertJsonPath('data.agent_id', $this->agent->id);
});

it('refuse le renouvellement si l’agent a été supprimé', function () {
    $this->agent->delete();

    ($this->renouveler)()
        ->assertStatus(422)
        ->assertJsonValidationErrors(['contrat']);
});

it('refuse le renouvellement sans le droit de gérer les contrats', function () {
    $lecteur = User::factory()->create();
    $lecteur->givePermissionTo('contrats.view');

    $this->actingAs($lecteur)
        ->postJson("/api/v1/contrats/{$this->contrat->id}/renouveler", [
            'type' => 'cdd',
            'date_debut' => '2027-01-01',
            'date_fin' => '2027-12-31',
        ])
        ->assertForbidden();

    expect(Contrat::query()->where('agent_id', $this->agent->id)->count())->toBe(1);
});

it('signale dans la liste les contrats déjà renouvelés', function () {
    $id = ($this->renouveler)()->assertCreated()->json('data.id');

    $rows = collect($this->actingAs($this->admin)
        ->getJson("/api/v1/contrats?agent_id={$this->agent->id}")
        ->assertOk()
        ->json('data'))->keyBy('id');

    expect($rows[$this->contrat->id]['est_renouvele'])->toBeTrue()
        ->and($rows[$id]['est_renouvele'])->toBeFalse();
});

it('recalcule le brut quand une prime est vidée à la modification', function () {
    $contrat = Contrat::query()->create([
        'agent_id' => $this->agent->id,
        'type' => 'cdd',
        'date_debut' => '2028-01-01',
        'date_fin' => '2028-12-31',
        'salaire_base' => 200000,
        'prime_transport' => 30000,
        'statut' => 'suspendu',
    ]);

    $response = $this->actingAs($this->admin)
        ->putJson("/api/v1/contrats/{$contrat->id}", ['prime_transport' => null])
        ->assertOk();

    expect((float) $response->json('data.salaire_brut'))->toBe(200000.0);
});

it('garde les montants non envoyés à la modification', function () {
    $contrat = Contrat::query()->create([
        'agent_id' => $this->agent->id,
        'type' => 'cdd',
        'date_debut' => '2028-01-01',
        'date_fin' => '2028-12-31',
        'salaire_base' => 200000,
        'prime_transport' => 30000,
        'statut' => 'suspendu',
    ]);

    $response = $this->actingAs($this->admin)
        ->putJson("/api/v1/contrats/{$contrat->id}", ['date_fin' => '2028-06-30'])
        ->assertOk();

    expect((float) $response->json('data.salaire_brut'))->toBe(230000.0);
});
