<?php

/*
 * Retours client — planning :
 * 1. Un planning sur une période ne doit pas disparaître le jour suivant.
 * 2. Un remplaçant programmé sur un site reste affectable sur un autre site.
 * 3. Un agent qui fait 1 jour ou un quart peut être reconduit comme
 *    remplaçant le jour où son collègue est absent.
 */

use App\Domain\Shared\Enums\StatutVacation;
use App\Domain\Shared\Enums\TypeAgent;
use App\Domain\Shared\Support\ShiftSchedule;
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
        'email' => 'admin-periode@sis.ci',
        'password' => Hash::make('password'),
    ]);
    $this->admin->assignRole('super-admin');

    $grade = Grade::query()->create(['libelle' => 'Agent', 'type_agent' => TypeAgent::Agent]);
    $makeAgent = fn (string $matricule) => Agent::query()->create([
        'grade_id' => $grade->id,
        'type' => TypeAgent::Agent,
        'nom' => 'Agent',
        'prenom' => $matricule,
        'matricule' => $matricule,
        'statut' => 'disponible',
    ]);
    $this->titulaire = $makeAgent('AG-P1');
    $this->remplacant = $makeAgent('AG-P2');

    $client = Client::query()->create(['type' => 'entreprise', 'raison_sociale' => 'Client P', 'statut' => 'actif']);
    $zone = Zone::query()->create(['nom' => 'Zone P']);
    $makeSite = fn (string $nom) => Site::query()->create([
        'client_id' => $client->id,
        'zone_id' => $zone->id,
        'nom' => $nom,
    ]);
    $this->siteA = $makeSite('Site A');
    $this->siteB = $makeSite('Site B');

    $this->posteNuitA = Poste::query()->create([
        'site_id' => $this->siteA->id,
        'nom' => 'Nuit A',
        'agents_requis' => 1,
        'heure_debut' => '19:00',
        'heure_fin' => '07:00',
    ]);
    $this->posteJourA = Poste::query()->create([
        'site_id' => $this->siteA->id,
        'nom' => 'Jour A',
        'agents_requis' => 1,
        'heure_debut' => '07:00',
        'heure_fin' => '19:00',
    ]);
    $this->posteJourB = Poste::query()->create([
        'site_id' => $this->siteB->id,
        'nom' => 'Jour B',
        'agents_requis' => 1,
        'heure_debut' => '07:00',
        'heure_fin' => '19:00',
    ]);
});

function coverageStatut($test, string $siteId, string $posteId, string $date): ?string
{
    $rows = $test->actingAs($test->admin)
        ->getJson("/api/v1/postes/coverage?site_id={$siteId}&date={$date}")
        ->assertOk()
        ->json('data');

    return collect($rows)->firstWhere('poste_id', $posteId)['statut'] ?? null;
}

// ── 1. Planning sur période ──────────────────────────────────────────────

it('découpe une affectation sur période en une vacation par jour', function () {
    $this->actingAs($this->admin)->postJson('/api/v1/vacations', [
        'agent_id' => $this->titulaire->id,
        'site_id' => $this->siteA->id,
        'poste_id' => $this->posteJourA->id,
        'date_debut' => '2026-11-02',
        'date_fin' => '2026-11-06',
        'heure_debut' => '07:00',
        'heure_fin' => '19:00',
    ])->assertCreated()->assertJsonPath('meta.created', 5);

    $dates = Vacation::query()->where('agent_id', $this->titulaire->id)
        ->orderBy('date_debut')->get()
        ->map(fn (Vacation $v) => [$v->date_debut->toDateString(), $v->date_fin->toDateString()])
        ->all();

    expect($dates)->toBe([
        ['2026-11-02', '2026-11-02'],
        ['2026-11-03', '2026-11-03'],
        ['2026-11-04', '2026-11-04'],
        ['2026-11-05', '2026-11-05'],
        ['2026-11-06', '2026-11-06'],
    ]);
});

it('garde une nuit planifiée sur période visible chaque jour (plus de disparition)', function () {
    $this->actingAs($this->admin)->postJson('/api/v1/vacations', [
        'agent_id' => $this->titulaire->id,
        'site_id' => $this->siteA->id,
        'poste_id' => $this->posteNuitA->id,
        'date_debut' => '2026-11-02',
        'date_fin' => '2026-11-04',
        'heure_debut' => '19:00',
        'heure_fin' => '07:00',
    ])->assertCreated()->assertJsonPath('meta.created', 3);

    foreach (['2026-11-02', '2026-11-03', '2026-11-04'] as $jour) {
        expect(coverageStatut($this, $this->siteA->id, $this->posteNuitA->id, $jour))->toBe('ok');
    }
    expect(coverageStatut($this, $this->siteA->id, $this->posteNuitA->id, '2026-11-05'))->toBe('non_couvert');
});

it('couvre chaque nuit d’une vacation sur période déjà enregistrée (données existantes)', function () {
    // Ancienne saisie : une seule ligne du 02 au 05 (dernière relève le 05 au matin).
    Vacation::query()->create([
        'agent_id' => $this->titulaire->id,
        'site_id' => $this->siteA->id,
        'poste_id' => $this->posteNuitA->id,
        'date_debut' => '2026-11-02',
        'date_fin' => '2026-11-05',
        'heure_debut' => '19:00',
        'heure_fin' => '07:00',
        'statut' => StatutVacation::Planifiee->value,
    ]);

    expect(coverageStatut($this, $this->siteA->id, $this->posteNuitA->id, '2026-11-03'))->toBe('ok')
        ->and(coverageStatut($this, $this->siteA->id, $this->posteNuitA->id, '2026-11-04'))->toBe('ok')
        ->and(coverageStatut($this, $this->siteA->id, $this->posteNuitA->id, '2026-11-05'))->toBe('non_couvert');
});

it('refuse une période de plus d’un an', function () {
    $this->actingAs($this->admin)->postJson('/api/v1/vacations', [
        'agent_id' => $this->titulaire->id,
        'site_id' => $this->siteA->id,
        'date_debut' => '2026-01-01',
        'date_fin' => '2027-06-01',
        'heure_debut' => '07:00',
        'heure_fin' => '19:00',
    ])->assertUnprocessable()->assertJsonValidationErrors('date_fin');
});

// ── 2. Remplaçant sur un autre site ─────────────────────────────────────

it('laisse un agent planifié de jour sur période libre pour une autre plage ailleurs', function () {
    // Ancienne saisie continue : jour 07–19 du 02 au 30 sur le site A.
    Vacation::query()->create([
        'agent_id' => $this->remplacant->id,
        'site_id' => $this->siteA->id,
        'date_debut' => '2026-11-02',
        'date_fin' => '2026-11-30',
        'heure_debut' => '07:00',
        'heure_fin' => '13:00',
        'statut' => StatutVacation::Planifiee->value,
    ]);

    // Avant : bloc continu 02/11 07:00 → 30/11 13:00, tout était en conflit.
    // Désormais seuls les créneaux 07–13 de chaque jour occupent l’agent.
    $this->actingAs($this->admin)->postJson('/api/v1/vacations', [
        'agent_id' => $this->remplacant->id,
        'site_id' => $this->siteB->id,
        'date_debut' => '2026-11-10',
        'date_fin' => '2026-11-10',
        'heure_debut' => '00:00',
        'heure_fin' => '06:00',
        'remplacement' => true,
    ])->assertCreated();
});

it('refuse toujours un vrai chevauchement horaire, même en remplacement', function () {
    $this->actingAs($this->admin)->postJson('/api/v1/vacations', [
        'agent_id' => $this->remplacant->id,
        'site_id' => $this->siteA->id,
        'date_debut' => '2026-11-10',
        'date_fin' => '2026-11-10',
        'heure_debut' => '07:00',
        'heure_fin' => '13:00',
    ])->assertCreated();

    $this->actingAs($this->admin)->postJson('/api/v1/vacations', [
        'agent_id' => $this->remplacant->id,
        'site_id' => $this->siteB->id,
        'date_debut' => '2026-11-10',
        'date_fin' => '2026-11-10',
        'heure_debut' => '12:00',
        'heure_fin' => '18:00',
        'remplacement' => true,
    ])->assertStatus(409);
});

it('permet de recouvrir sur un site B avec un remplaçant déjà posté sur le site A', function () {
    // Remplaçant : quart du matin sur le site A.
    $this->actingAs($this->admin)->postJson('/api/v1/vacations', [
        'agent_id' => $this->remplacant->id,
        'site_id' => $this->siteA->id,
        'date_debut' => '2026-11-10',
        'date_fin' => '2026-11-10',
        'heure_debut' => '06:00',
        'heure_fin' => '12:00',
    ])->assertCreated();

    // Trou sur le site B l’après-midi (titulaire absent).
    $trou = Vacation::query()->create([
        'agent_id' => $this->titulaire->id,
        'site_id' => $this->siteB->id,
        'date_debut' => '2026-11-10',
        'date_fin' => '2026-11-10',
        'heure_debut' => '13:00',
        'heure_fin' => '19:00',
        'statut' => StatutVacation::ARecouvrir->value,
    ]);

    $this->actingAs($this->admin)
        ->postJson("/api/v1/vacations/{$trou->id}/recouvrir", ['agent_id' => $this->remplacant->id])
        ->assertSuccessful()
        ->assertJsonPath('data.site_id', $this->siteB->id);

    expect($trou->fresh()->statut)->toBe(StatutVacation::Annulee);
});

it('découpe le recouvrement d’un trou sur période en vacations journalières', function () {
    $trou = Vacation::query()->create([
        'agent_id' => $this->titulaire->id,
        'site_id' => $this->siteB->id,
        'poste_id' => $this->posteJourB->id,
        'date_debut' => '2026-11-10',
        'date_fin' => '2026-11-12',
        'heure_debut' => '07:00',
        'heure_fin' => '19:00',
        'statut' => StatutVacation::ARecouvrir->value,
    ]);

    $this->actingAs($this->admin)
        ->postJson("/api/v1/vacations/{$trou->id}/recouvrir", ['agent_id' => $this->remplacant->id])
        ->assertSuccessful();

    expect(Vacation::query()->where('agent_id', $this->remplacant->id)->count())->toBe(3);
});

// ── 3. Reconduction le jour où le collègue est absent ───────────────────

it('bloque l’enchaînement sans repos hors remplacement', function () {
    $this->actingAs($this->admin)->postJson('/api/v1/vacations', [
        'agent_id' => $this->remplacant->id,
        'site_id' => $this->siteA->id,
        'poste_id' => $this->posteJourA->id,
        'date_debut' => '2026-11-10',
        'date_fin' => '2026-11-10',
        'heure_debut' => '07:00',
        'heure_fin' => '19:00',
    ])->assertCreated();

    // Même soir sur un autre site : 1h de repos seulement → refus.
    $this->actingAs($this->admin)->postJson('/api/v1/vacations', [
        'agent_id' => $this->remplacant->id,
        'site_id' => $this->siteB->id,
        'date_debut' => '2026-11-10',
        'date_fin' => '2026-11-10',
        'heure_debut' => '20:00',
        'heure_fin' => '23:00',
    ])->assertUnprocessable();
});

it('reconduit un agent de jour sur la nuit du collègue absent (remplacement)', function () {
    $this->actingAs($this->admin)->postJson('/api/v1/vacations', [
        'agent_id' => $this->remplacant->id,
        'site_id' => $this->siteA->id,
        'poste_id' => $this->posteJourA->id,
        'date_debut' => '2026-11-10',
        'date_fin' => '2026-11-10',
        'heure_debut' => '07:00',
        'heure_fin' => '19:00',
    ])->assertCreated();

    // Collègue de nuit absent sur le site B : l’agent de jour est reconduit.
    $this->actingAs($this->admin)->postJson('/api/v1/vacations', [
        'agent_id' => $this->remplacant->id,
        'site_id' => $this->siteB->id,
        'date_debut' => '2026-11-10',
        'date_fin' => '2026-11-10',
        'heure_debut' => '20:00',
        'heure_fin' => '23:00',
        'remplacement' => true,
    ])->assertCreated();
});

it('reconduit un agent d’un quart de jour sur le trou du collègue absent', function () {
    // Quart du matin du remplaçant sur le site B.
    $this->actingAs($this->admin)->postJson('/api/v1/vacations', [
        'agent_id' => $this->remplacant->id,
        'site_id' => $this->siteB->id,
        'date_debut' => '2026-11-10',
        'date_fin' => '2026-11-10',
        'heure_debut' => '07:00',
        'heure_fin' => '13:00',
    ])->assertCreated();

    // Le collègue de nuit du site A est absent → trou.
    $trou = Vacation::query()->create([
        'agent_id' => $this->titulaire->id,
        'site_id' => $this->siteA->id,
        'poste_id' => $this->posteNuitA->id,
        'date_debut' => '2026-11-10',
        'date_fin' => '2026-11-11',
        'heure_debut' => '19:00',
        'heure_fin' => '07:00',
        'statut' => StatutVacation::ARecouvrir->value,
    ]);

    $this->actingAs($this->admin)
        ->postJson("/api/v1/vacations/{$trou->id}/recouvrir", ['agent_id' => $this->remplacant->id])
        ->assertSuccessful();
});

// ── ShiftSchedule (unitaire) ────────────────────────────────────────────

it('calcule les jours de prise de poste jour / nuit / période', function () {
    expect(ShiftSchedule::fromStored('2026-11-02', '07:00', '19:00', '2026-11-04')->days())
        ->toBe(['2026-11-02', '2026-11-03', '2026-11-04'])
        ->and(ShiftSchedule::fromStored('2026-11-02', '19:00', '07:00', '2026-11-03')->days())
        ->toBe(['2026-11-02'])
        ->and(ShiftSchedule::fromStored('2026-11-02', '19:00', '07:00', '2026-11-05')->days())
        ->toBe(['2026-11-02', '2026-11-03', '2026-11-04'])
        ->and(ShiftSchedule::fromStored('2026-11-02', '06:30', '06:30', '2026-11-03')->days())
        ->toBe(['2026-11-02']);
});

it('détecte un chevauchement nuit → matin suivant et ignore les plages disjointes', function () {
    $nuit = ShiftSchedule::fromStored('2026-11-02', '20:00', '06:00', '2026-11-03');

    expect($nuit->overlaps(ShiftSchedule::fromStored('2026-11-03', '05:00', '09:00', '2026-11-03')))->toBeTrue()
        ->and($nuit->overlaps(ShiftSchedule::fromStored('2026-11-03', '07:00', '12:00', '2026-11-03')))->toBeFalse()
        ->and($nuit->closestGap(ShiftSchedule::fromStored('2026-11-03', '07:00', '12:00', '2026-11-03'))['gap'])->toBe(60);
});
