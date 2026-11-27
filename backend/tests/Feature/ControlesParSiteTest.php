<?php

/*
 * Contrôles regroupés par jour et par site : à contrôler, contrôlés,
 * non contrôlés, détail par agent.
 */

use App\Domain\Shared\Enums\StatutVacation;
use App\Domain\Shared\Enums\TypeAgent;
use App\Models\Agent;
use App\Models\Client;
use App\Models\Controle;
use App\Models\Grade;
use App\Models\Site;
use App\Models\User;
use App\Models\Vacation;
use App\Models\Zone;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->admin = User::factory()->create(['email' => 'admin-cps@sis.ci', 'password' => Hash::make('password')]);
    $this->admin->assignRole('super-admin');

    $grade = Grade::query()->create(['libelle' => 'Agent', 'type_agent' => TypeAgent::Agent]);
    $agent = fn (string $mat) => Agent::query()->create([
        'grade_id' => $grade->id, 'type' => TypeAgent::Agent, 'nom' => 'Agent', 'prenom' => $mat,
        'matricule' => $mat, 'statut' => 'en_activite',
    ]);
    $this->a1 = $agent('CPS1');
    $this->a2 = $agent('CPS2');
    $this->a3 = $agent('CPS3');
    $this->horsPlanning = $agent('CPS4');

    $client = Client::query()->create(['type' => 'entreprise', 'raison_sociale' => 'Client CPS', 'statut' => 'actif']);
    $zone = Zone::query()->create(['nom' => 'Zone CPS']);
    $this->siteA = Site::query()->create(['client_id' => $client->id, 'zone_id' => $zone->id, 'nom' => 'Site A']);
    $this->siteB = Site::query()->create(['client_id' => $client->id, 'zone_id' => $zone->id, 'nom' => 'Site B']);

    $vac = fn (Agent $a, Site $s, string $debut, string $fin, string $hd, string $hf) => Vacation::query()->create([
        'agent_id' => $a->id, 'site_id' => $s->id, 'date_debut' => $debut, 'date_fin' => $fin,
        'heure_debut' => $hd, 'heure_fin' => $hf, 'statut' => StatutVacation::Planifiee->value,
    ]);
    // Site A : a1 jour (contrôlé présent), a2 nuit (contrôlé absent).
    $vac($this->a1, $this->siteA, '2026-11-10', '2026-11-10', '07:00', '19:00');
    $vac($this->a2, $this->siteA, '2026-11-10', '2026-11-11', '19:00', '07:00');
    // Site B : a3 jour, jamais contrôlé.
    $vac($this->a3, $this->siteB, '2026-11-10', '2026-11-10', '07:00', '19:00');
    // a2 travaillait aussi la nuit précédente : un contrôle à 02:00 le 10 la concerne, pas le 10 au soir.
    $vac($this->a2, $this->siteA, '2026-11-09', '2026-11-10', '19:00', '07:00');

    $ctl = fn (Agent $a, Site $s, string $at, string $r) => Controle::query()->create([
        'site_id' => $s->id, 'controle_agent_id' => $a->id, 'effectue_at' => $at, 'resultat' => $r,
    ]);
    $ctl($this->a1, $this->siteA, '2026-11-10 10:00:00', 'present');
    $ctl($this->a2, $this->siteA, '2026-11-10 02:00:00', 'present'); // nuit du 9
    $ctl($this->a2, $this->siteA, '2026-11-10 22:00:00', 'absent');
    $ctl($this->horsPlanning, $this->siteA, '2026-11-10 11:00:00', 'present');
});

it('compte par site les agents à contrôler, contrôlés et non contrôlés', function () {
    $res = $this->actingAs($this->admin)
        ->getJson('/api/v1/controles/par-site?date=2026-11-10')
        ->assertOk()
        ->json('data');

    expect($res['totaux'])->toMatchArray([
        'sites' => 2, 'a_controler' => 3, 'controles' => 2, 'presents' => 1,
        'absents' => 1, 'non_controles' => 1, 'hors_planning' => 1, 'sites_complets' => 1,
    ]);

    // Le moins couvert d’abord.
    expect($res['sites'][0]['site'])->toBe('Site B')
        ->and($res['sites'][0]['non_controles'])->toBe(1);

    $a = collect($res['sites'])->firstWhere('site', 'Site A');
    expect($a['a_controler'])->toBe(2)
        ->and($a['controles'])->toBe(2)
        ->and($a['hors_planning'])->toBe(1);

    $etats = collect($a['lignes'])->mapWithKeys(fn ($l) => [$l['agent']['matricule'] => $l['etat']])->all();
    expect($etats)->toBe(['CPS2' => 'absent', 'CPS4' => 'hors_planning', 'CPS1' => 'present']);

    // Le contrôle de 02:00 est rattaché à la nuit du 9, pas à celle du 10.
    $nuit = collect($a['lignes'])->firstWhere('agent.matricule', 'CPS2');
    expect($nuit['nb_controles'])->toBe(1)
        ->and($nuit['controle']['resultat'])->toBe('absent');
});

it('exige une date valide', function () {
    $this->actingAs($this->admin)->getJson('/api/v1/controles/par-site')->assertStatus(422);
});

it('ne compte un contrôle que sur le site où il a été fait (agent sur deux sites)', function () {
    // a3 : jour sur B (déjà), puis nuit sur A le même jour.
    Vacation::query()->create([
        'agent_id' => $this->a3->id, 'site_id' => $this->siteA->id, 'date_debut' => '2026-11-10',
        'date_fin' => '2026-11-11', 'heure_debut' => '19:00', 'heure_fin' => '07:00',
        'statut' => StatutVacation::Planifiee->value,
    ]);
    // Contrôle à 19:10 sur B : concerne la fin du jour sur B, pas la nuit sur A.
    Controle::query()->create([
        'site_id' => $this->siteB->id, 'controle_agent_id' => $this->a3->id,
        'effectue_at' => '2026-11-10 19:10:00', 'resultat' => 'present',
    ]);

    $query = app(\App\Application\Reporting\ControlesParSiteQuery::class);
    $etat = fn (array $res, string $site) => collect($res['sites'])->firstWhere('site', $site)['lignes'];

    $tous = $query->jour('2026-11-10');
    expect(collect($etat($tous, 'Site B'))->firstWhere('agent.matricule', 'CPS3')['etat'])->toBe('present')
        ->and(collect($etat($tous, 'Site A'))->firstWhere('agent.matricule', 'CPS3')['etat'])->toBe('non_controle');

    // Vue limitée au site A (périmètre d’un contrôleur) : le contrôle fait sur B n’y apparaît pas.
    $perimetreA = $query->jour('2026-11-10', [$this->siteA->id]);
    $ligne = collect($etat($perimetreA, 'Site A'))->firstWhere('agent.matricule', 'CPS3');
    expect($ligne['etat'])->toBe('non_controle')->and($ligne['controle'])->toBeNull();
});

it('ne plante pas quand un site supprimé garde des vacations', function () {
    $this->siteB->delete();

    $this->actingAs($this->admin)
        ->getJson('/api/v1/controles/par-site?date=2026-11-10')
        ->assertOk();
});
