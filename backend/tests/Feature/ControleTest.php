<?php

use App\Domain\Shared\Enums\TypeAgent;
use App\Models\Agent;
use App\Models\Anomalie;
use App\Models\Client;
use App\Models\Controle;
use App\Models\Grade;
use App\Models\RondierPerimetre;
use App\Models\Site;
use App\Models\User;
use App\Models\Vacation;
use App\Models\Zone;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

// 1x1 PNG minimal valide, réutilisé comme "preuve photo" dans tous les tests.
const CONTROLE_PHOTO_B64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

beforeEach(function () {
    Carbon::setTestNow();
    $this->seed(RolePermissionSeeder::class);

    $this->admin = User::factory()->create([
        'email' => 'admin-controle@sis.ci',
        'password' => Hash::make('password'),
    ]);
    $this->admin->assignRole('super-admin');

    $gradeControleur = Grade::query()->create([
        'libelle' => 'Contrôleur',
        'type_agent' => TypeAgent::Controleur,
    ]);
    $gradeAgent = Grade::query()->create([
        'libelle' => 'Agent',
        'type_agent' => TypeAgent::Agent,
    ]);

    $this->controleur = Agent::query()->create([
        'grade_id' => $gradeControleur->id,
        'type' => TypeAgent::Controleur,
        'nom' => 'Kone',
        'prenom' => 'Mohamed',
        'matricule' => 'RD-CTRL-1',
        'statut' => 'en_activite',
    ]);

    $this->agentPoste = Agent::query()->create([
        'grade_id' => $gradeAgent->id,
        'type' => TypeAgent::Agent,
        'nom' => 'Traore',
        'prenom' => 'Awa',
        'matricule' => 'AG-CTRL-1',
        'statut' => 'en_activite',
    ]);

    $client = Client::query()->create([
        'type' => 'entreprise',
        'raison_sociale' => 'Client Controle',
        'statut' => 'actif',
    ]);
    $this->zone = Zone::query()->create(['nom' => 'Zone Controle']);
    $this->site = Site::query()->create([
        'client_id' => $client->id,
        'zone_id' => $this->zone->id,
        'nom' => 'Site Controle',
        'latitude' => 5.3,
        'longitude' => -4.0,
        'rayon_metres' => 200,
    ]);

    RondierPerimetre::query()->create([
        'agent_id' => $this->controleur->id,
        'zone_id' => $this->zone->id,
        'site_id' => null,
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
});

function controlePayload(TestCase $test, array $overrides = []): array
{
    return array_merge([
        'agent_id' => $test->controleur->id,
        'controle_agent_id' => $test->agentPoste->id,
        'site_id' => $test->site->id,
        'latitude' => 5.3,
        'longitude' => -4.0,
        'resultat' => 'present',
        'photo_base64' => CONTROLE_PHOTO_B64,
    ], $overrides);
}

it('stores the resultat as a real field and does not create an anomalie when present', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-20 10:00:00'));
    $vacation = Vacation::query()->create([
        'agent_id' => $this->agentPoste->id,
        'site_id' => $this->site->id,
        'date_debut' => '2026-07-20',
        'date_fin' => null,
        'heure_debut' => '08:00:00',
        'heure_fin' => '17:00:00',
        'statut' => 'planifiee',
    ]);

    $response = $this->actingAs($this->admin)->postJson('/api/v1/controles', controlePayload($this));

    $response->assertCreated()->assertJsonPath('data.resultat', 'present');
    expect(Anomalie::query()->count())->toBe(0);
    expect(\App\Models\Absence::query()->count())->toBe(0);
    expect($vacation->fresh()->statut->value)->toBe('planifiee');
});

it('creates an anomalie, RH absence and marks the vacation a_recouvrir when absent', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-20 10:00:00'));
    $vacation = Vacation::query()->create([
        'agent_id' => $this->agentPoste->id,
        'site_id' => $this->site->id,
        'date_debut' => '2026-07-20',
        'date_fin' => null,
        'heure_debut' => '08:00:00',
        'heure_fin' => '17:00:00',
        'statut' => 'planifiee',
    ]);

    $response = $this->actingAs($this->admin)->postJson(
        '/api/v1/controles',
        controlePayload($this, ['resultat' => 'absent'])
    );

    $response->assertCreated()->assertJsonPath('data.resultat', 'absent');
    expect(Anomalie::query()->count())->toBe(1);
    $anomalie = Anomalie::query()->first();
    expect($anomalie->type->value)->toBe('absence_poste')
        ->and($anomalie->gravite->value)->toBe('haute')
        ->and($anomalie->statut->value)->toBe('ouverte')
        ->and($anomalie->site_id)->toBe($this->site->id);

    $absence = \App\Models\Absence::query()->first();
    expect($absence)->not->toBeNull()
        ->and($absence->agent_id)->toBe($this->agentPoste->id)
        ->and($absence->statut->value)->toBe('approuvee')
        ->and($absence->type->value)->toBe('autre')
        ->and($absence->source->value)->toBe('controle')
        ->and($absence->controle_id)->not->toBeNull()
        ->and($absence->date_debut->toDateString())->toBe('2026-07-20')
        ->and($absence->date_fin->toDateString())->toBe('2026-07-20');

    $vacation->refresh();
    expect($vacation->statut->value)->toBe('a_recouvrir')
        ->and($vacation->absence_id)->toBe($absence->id);

    // Statut agent inchangé (pas congé/maladie).
    expect($this->agentPoste->fresh()->statut->value)->toBe('en_activite');
});

it('reuses an existing same-day absence on a later absent recheck', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-20 10:00:00'));
    Vacation::query()->create([
        'agent_id' => $this->agentPoste->id,
        'site_id' => $this->site->id,
        'date_debut' => '2026-07-20',
        'date_fin' => null,
        'heure_debut' => '08:00:00',
        'heure_fin' => '17:00:00',
        'statut' => 'planifiee',
    ]);

    $this->actingAs($this->admin)->postJson(
        '/api/v1/controles',
        controlePayload($this, ['resultat' => 'absent', 'client_uuid' => (string) Str::uuid()])
    )->assertCreated();

    Carbon::setTestNow(Carbon::parse('2026-07-20 10:31:00'));

    $this->actingAs($this->admin)->postJson(
        '/api/v1/controles',
        controlePayload($this, ['resultat' => 'absent', 'client_uuid' => (string) Str::uuid()])
    )->assertCreated();

    expect(\App\Models\Absence::query()->count())->toBe(1);
    expect(Anomalie::query()->count())->toBe(2);
});

it('rejects a control outside the vacation time window even with an active vacation today', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-20 14:00:00'));
    // Vacation de nuit : l'agent n'est censé être en poste que 19h-07h.
    Vacation::query()->create([
        'agent_id' => $this->agentPoste->id,
        'site_id' => $this->site->id,
        'date_debut' => '2026-07-20',
        'date_fin' => null,
        'heure_debut' => '19:00:00',
        'heure_fin' => '07:00:00',
        'statut' => 'planifiee',
    ]);

    $this->actingAs($this->admin)->postJson('/api/v1/controles', controlePayload($this))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['controle_agent_id']);
});

it('allows a control inside the overnight vacation window', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-20 23:00:00'));
    Vacation::query()->create([
        'agent_id' => $this->agentPoste->id,
        'site_id' => $this->site->id,
        'date_debut' => '2026-07-20',
        'date_fin' => null,
        'heure_debut' => '19:00:00',
        'heure_fin' => '07:00:00',
        'statut' => 'planifiee',
    ]);

    $this->actingAs($this->admin)->postJson('/api/v1/controles', controlePayload($this))
        ->assertCreated();
});

it('rejects a recheck of the same agent within the recheck window', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-20 10:00:00'));
    Vacation::query()->create([
        'agent_id' => $this->agentPoste->id,
        'site_id' => $this->site->id,
        'date_debut' => '2026-07-20',
        'date_fin' => null,
        'heure_debut' => '08:00:00',
        'heure_fin' => '17:00:00',
        'statut' => 'planifiee',
    ]);

    $this->actingAs($this->admin)->postJson(
        '/api/v1/controles',
        controlePayload($this, ['client_uuid' => (string) Str::uuid()])
    )->assertCreated();

    $this->actingAs($this->admin)->postJson(
        '/api/v1/controles',
        controlePayload($this, ['client_uuid' => (string) Str::uuid()])
    )->assertStatus(422)->assertJsonValidationErrors(['controle_agent_id']);
});

it('allows a recheck once the recheck window has passed', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-20 10:00:00'));
    Vacation::query()->create([
        'agent_id' => $this->agentPoste->id,
        'site_id' => $this->site->id,
        'date_debut' => '2026-07-20',
        'date_fin' => null,
        'heure_debut' => '08:00:00',
        'heure_fin' => '17:00:00',
        'statut' => 'planifiee',
    ]);

    $this->actingAs($this->admin)->postJson(
        '/api/v1/controles',
        controlePayload($this, ['resultat' => 'absent', 'client_uuid' => (string) Str::uuid()])
    )->assertCreated();

    Carbon::setTestNow(Carbon::parse('2026-07-20 10:31:00'));

    $this->actingAs($this->admin)->postJson(
        '/api/v1/controles',
        controlePayload($this, ['resultat' => 'present', 'client_uuid' => (string) Str::uuid()])
    )->assertCreated();

    expect(Controle::query()->count())->toBe(2);
});

it('ignores a client-supplied effectue_at and always stores the server time', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-20 10:00:00'));
    Vacation::query()->create([
        'agent_id' => $this->agentPoste->id,
        'site_id' => $this->site->id,
        'date_debut' => '2026-07-20',
        'date_fin' => null,
        'heure_debut' => '08:00:00',
        'heure_fin' => '17:00:00',
        'statut' => 'planifiee',
    ]);

    $response = $this->actingAs($this->admin)->postJson(
        '/api/v1/controles',
        controlePayload($this, ['effectue_at' => '2020-01-01T00:00:00Z'])
    );

    $response->assertCreated();
    $controle = Controle::query()->first();
    expect($controle->effectue_at->toDateString())->toBe('2026-07-20');
});
