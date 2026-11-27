<?php

/*
 * Retour client 4 — un agent non contrôlé n’est ni présent ni absent :
 * son créneau reste tel quel et ses heures ne sont pas comptabilisées.
 */

use App\Application\Reporting\PointageVacationsAction;
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
    $this->admin = User::factory()->create([
        'email' => 'admin-pointage@sis.ci',
        'password' => Hash::make('password'),
    ]);
    $this->admin->assignRole('super-admin');

    $grade = Grade::query()->create(['libelle' => 'Agent', 'type_agent' => TypeAgent::Agent]);
    $this->agent = Agent::query()->create([
        'grade_id' => $grade->id,
        'type' => TypeAgent::Agent,
        'nom' => 'Kouassi',
        'prenom' => 'Yao',
        'matricule' => 'AG-PT1',
        'statut' => 'en_activite',
    ]);

    $client = Client::query()->create(['type' => 'entreprise', 'raison_sociale' => 'Client PT', 'statut' => 'actif']);
    $zone = Zone::query()->create(['nom' => 'Zone PT']);
    $this->site = Site::query()->create([
        'client_id' => $client->id,
        'zone_id' => $zone->id,
        'nom' => 'Site PT',
    ]);

    // Trois jours de 07:00 à 19:00 (12 h).
    foreach (['2026-11-02', '2026-11-03', '2026-11-04'] as $jour) {
        Vacation::query()->create([
            'agent_id' => $this->agent->id,
            'site_id' => $this->site->id,
            'date_debut' => $jour,
            'date_fin' => $jour,
            'heure_debut' => '07:00',
            'heure_fin' => '19:00',
            'statut' => StatutVacation::Planifiee->value,
        ]);
    }

    $controle = fn (string $at, string $resultat) => Controle::query()->create([
        'site_id' => $this->site->id,
        'controle_agent_id' => $this->agent->id,
        'effectue_at' => $at,
        'resultat' => $resultat,
    ]);
    $controle('2026-11-02 10:00:00', 'present');
    $controle('2026-11-03 09:30:00', 'absent');
    // 04/11 : aucun contrôle.
});

it('classe chaque créneau présent / absent / non contrôlé', function () {
    $lignes = (new PointageVacationsAction)->execute(
        Vacation::query()->get(),
        '2026-11-01',
        '2026-11-30',
    );

    $parJour = collect($lignes)->mapWithKeys(fn ($l) => [$l['date'] => $l['controle']])->sortKeys()->all();

    expect($parJour)->toBe([
        '2026-11-02' => 'present',
        '2026-11-03' => 'absent',
        '2026-11-04' => 'non_controle',
    ]);
});

it('ne compte que les heures des créneaux contrôlés présents', function () {
    $lignes = (new PointageVacationsAction)->execute(Vacation::query()->get(), '2026-11-01', '2026-11-30');
    $totaux = PointageVacationsAction::totaux($lignes);

    expect($totaux)->toBe([
        'present' => 1,
        'absent' => 1,
        'non_controle' => 1,
        'heures_comptees' => 12.0,
        'heures_supplementaires' => 0.0,
        'jours_supplementaires' => 0,
    ]);
});

it('ignore un contrôle hors du créneau (autre jour)', function () {
    Controle::query()->create([
        'site_id' => $this->site->id,
        'controle_agent_id' => $this->agent->id,
        'effectue_at' => '2026-11-05 10:00:00',
        'resultat' => 'present',
    ]);

    $lignes = (new PointageVacationsAction)->execute(Vacation::query()->get(), '2026-11-04', '2026-11-04');

    expect($lignes)->toHaveCount(1)
        ->and($lignes[0]['controle'])->toBe('non_controle')
        ->and($lignes[0]['heures_comptees'])->toBe(0.0);
});

it('expose le pointage et les totaux via l’API', function () {
    $this->actingAs($this->admin)
        ->getJson("/api/v1/vacations/pointage?agent_id={$this->agent->id}&date_debut=2026-11-01&date_fin=2026-11-30")
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('meta.non_controle', 1)
        ->assertJsonPath('meta.heures_comptees', 12)
        ->assertJsonPath('data.0.agent', 'Yao Kouassi (AG-PT1)')
        ->assertJsonPath('data.0.statut', 'planifiee');
});

it('construit la feuille de pointage agent × jour (1 / A / NC, total des présences)', function () {
    // Double vacation le 02/11 : nuit 19:00 → 07:00 contrôlée présente.
    Vacation::query()->create([
        'agent_id' => $this->agent->id,
        'site_id' => $this->site->id,
        'date_debut' => '2026-11-02',
        'date_fin' => '2026-11-02',
        'heure_debut' => '19:00',
        'heure_fin' => '07:00',
        'statut' => StatutVacation::Planifiee->value,
    ]);
    Controle::query()->create([
        'site_id' => $this->site->id,
        'controle_agent_id' => $this->agent->id,
        'effectue_at' => '2026-11-02 23:00:00',
        'resultat' => 'present',
    ]);

    $response = $this->actingAs($this->admin)
        ->getJson("/api/v1/vacations/pointage/feuille?agent_id={$this->agent->id}&date_debut=2026-10-26&date_fin=2026-11-25")
        ->assertOk();

    $feuille = $response->json('data');
    expect($feuille['jours'])->toHaveCount(31)
        ->and($feuille['jours'][0])->toBe(['date' => '2026-10-26', 'jour' => 'L'])
        ->and($feuille['categories'])->toHaveCount(1)
        ->and($feuille['categories'][0]['libelle'])->toBe('Agents postés');

    $ligne = $feuille['categories'][0]['agents'][0];
    expect($ligne['nom'])->toBe('KOUASSI YAO')
        ->and($ligne['jours']['2026-11-01'])->toBeNull()
        ->and($ligne['jours']['2026-11-02'])->toBe('2')
        ->and($ligne['jours']['2026-11-03'])->toBe('A')
        ->and($ligne['jours']['2026-11-04'])->toBe('NC')
        ->and($ligne['total'])->toBe(2);
});

it('exporte la feuille de pointage en PDF', function () {
    $this->actingAs($this->admin)
        ->get('/api/v1/vacations/pointage/feuille/export-pdf?date_debut=2026-10-26&date_fin=2026-11-25')
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('refuse une feuille de pointage de plus de deux mois', function () {
    $this->actingAs($this->admin)
        ->getJson('/api/v1/vacations/pointage/feuille?date_debut=2026-01-01&date_fin=2026-06-30')
        ->assertStatus(422);
});

it('donne accès à la feuille de pointage à la RH', function () {
    $rh = User::factory()->create(['email' => 'rh-pointage@sis.ci']);
    $rh->assignRole('rh');

    $this->actingAs($rh)
        ->getJson('/api/v1/vacations/pointage/feuille?date_debut=2026-10-26&date_fin=2026-11-25')
        ->assertOk();
});

it('n’ajoute les agents sans vacation que sur demande', function () {
    $grade = Grade::query()->first();
    $sansVacation = Agent::query()->create([
        'grade_id' => $grade->id,
        'type' => TypeAgent::Agent,
        'nom' => 'Sans',
        'prenom' => 'Vacation',
        'matricule' => 'AG-PT2',
        'statut' => 'en_activite',
    ]);
    $sansVacation->contrats()->create([
        'type' => 'cdi',
        'date_debut' => '2026-01-01',
        'statut' => 'actif',
        'salaire' => 100000,
    ]);

    $noms = fn (string $url) => collect($this->actingAs($this->admin)->getJson($url)->assertOk()->json('data.categories'))
        ->flatMap(fn ($c) => collect($c['agents'])->pluck('nom'))
        ->all();

    $base = '/api/v1/vacations/pointage/feuille?date_debut=2026-10-26&date_fin=2026-11-25';
    expect($noms($base))->toBe(['KOUASSI YAO'])
        ->and($noms($base.'&inclure_sans_vacation=1'))->toContain('SANS VACATION');
});
