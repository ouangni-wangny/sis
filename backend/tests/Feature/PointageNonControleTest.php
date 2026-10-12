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
