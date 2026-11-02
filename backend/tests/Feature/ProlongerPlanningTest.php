<?php

use App\Application\Operation\ProlongerPlanningPostesAction;
use App\Domain\Shared\Enums\StatutAgent;
use App\Domain\Shared\Enums\TypeAgent;
use App\Models\Agent;
use App\Models\Client;
use App\Models\Contrat;
use App\Models\Grade;
use App\Models\Poste;
use App\Models\Site;
use App\Models\User;
use App\Models\Vacation;
use App\Models\Zone;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    // Lundi 2 novembre 2026.
    Carbon::setTestNow('2026-11-02 00:20:00');
    $this->seed(RolePermissionSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('super-admin');

    $grade = Grade::query()->create(['libelle' => 'Agent', 'type_agent' => TypeAgent::Agent]);
    $this->creerAgent = function (string $nom) use ($grade): Agent {
        $agent = Agent::query()->create([
            'grade_id' => $grade->id,
            'type' => TypeAgent::Agent,
            'nom' => $nom,
            'prenom' => 'Agent',
            'matricule' => 'PL-'.$nom,
            'statut' => 'disponible',
        ]);
        Contrat::query()->where('agent_id', $agent->id)->forceDelete();
        Contrat::query()->create(['agent_id' => $agent->id, 'type' => 'cdi', 'date_debut' => '2026-01-01', 'statut' => 'actif']);

        return $agent;
    };

    $this->jour = ($this->creerAgent)('Jour');
    $this->nuit = ($this->creerAgent)('Nuit');

    $this->client = Client::query()->create(['type' => 'entreprise', 'raison_sociale' => 'Client', 'statut' => 'actif']);
    $this->site = Site::query()->create(['client_id' => $this->client->id, 'zone_id' => Zone::query()->create(['nom' => 'Z'])->id, 'nom' => 'Cocody']);
    $this->poste = Poste::query()->create([
        'site_id' => $this->site->id,
        'nom' => 'Entrée',
        'agents_requis' => 2,
        'heure_debut' => '06:30',
        'heure_fin' => '06:30',
    ]);

    /** Planifie $agent chaque jour de la période, sauf les jours ISO de $repos (1 = lundi). */
    $this->planifier = function (Agent $agent, string $du, string $au, string $debut = '06:30', string $fin = '18:30', array $repos = [], array $attributs = []) {
        for ($d = Carbon::parse($du); $d->lte($au); $d->addDay()) {
            if (in_array($d->isoWeekday(), $repos, true)) {
                continue;
            }
            Vacation::query()->create([
                'agent_id' => $agent->id,
                'site_id' => $this->site->id,
                'poste_id' => $this->poste->id,
                'date_debut' => $d->toDateString(),
                'date_fin' => $fin < $debut ? $d->copy()->addDay()->toDateString() : $d->toDateString(),
                'heure_debut' => $debut,
                'heure_fin' => $fin,
                'statut' => 'planifiee',
                ...$attributs,
            ]);
        }
    };

    $this->prolonger = fn (bool $reprendre = false) => app(ProlongerPlanningPostesAction::class)->execute(reprendre: $reprendre);

    $this->dates = fn (Agent $agent) => Vacation::query()
        ->where('agent_id', $agent->id)
        ->where('statut', '!=', 'annulee')
        ->orderBy('date_debut')
        ->get()
        ->map(fn (Vacation $v) => $v->date_debut->toDateString());
});

afterEach(function () {
    Carbon::setTestNow();
});

it('garde le planning rempli au moins un mois à l’avance, sans trou', function () {
    ($this->planifier)($this->jour, '2026-10-26', '2026-11-08');

    $resultat = ($this->prolonger)();

    $dates = ($this->dates)($this->jour);

    expect($resultat['created'])->toBeGreaterThan(30)
        ->and($dates->last())->toBe('2026-12-14')
        // Tous les jours, sans semaine vide.
        ->and($dates->count())->toBe((int) Carbon::parse('2026-10-26')->diffInDays('2026-12-14') + 1);
});

it('reconduit chaque agent sur ses horaires, nuit comprise', function () {
    ($this->planifier)($this->jour, '2026-10-26', '2026-11-08');
    ($this->planifier)($this->nuit, '2026-10-26', '2026-11-08', '18:30', '06:30');

    expect(($this->prolonger)()['skipped'])->toBe(0);

    $nuit = Vacation::query()->where('agent_id', $this->nuit->id)->whereDate('date_debut', '2026-12-01')->sole();

    expect(substr($nuit->heure_debut, 0, 5))->toBe('18:30')
        ->and($nuit->date_fin->toDateString())->toBe('2026-12-02')
        ->and($nuit->reconduite)->toBeTrue();
});

it('garde les jours de repos de l’agent', function () {
    ($this->planifier)($this->jour, '2026-10-26', '2026-11-08', repos: [7]);

    ($this->prolonger)();

    $dimanches = ($this->dates)($this->jour)->filter(fn ($d) => Carbon::parse($d)->isSunday());

    expect($dimanches)->toBeEmpty()
        ->and(($this->dates)($this->jour)->contains('2026-12-07'))->toBeTrue();
});

it('poursuit une alternance sur deux semaines dans le bon ordre', function () {
    // Semaine A : Jour travaille ; semaine B : Nuit travaille (même créneau).
    foreach (['2026-10-12' => $this->jour, '2026-10-19' => $this->nuit, '2026-10-26' => $this->jour, '2026-11-02' => $this->nuit] as $lundi => $agent) {
        ($this->planifier)($agent, $lundi, Carbon::parse($lundi)->addDays(6)->toDateString());
    }

    ($this->prolonger)();

    expect(($this->dates)($this->jour)->contains('2026-11-09'))->toBeTrue()
        ->and(($this->dates)($this->nuit)->contains('2026-11-09'))->toBeFalse()
        ->and(($this->dates)($this->nuit)->contains('2026-11-16'))->toBeTrue()
        ->and(($this->dates)($this->jour)->contains('2026-11-16'))->toBeFalse();
});

it('est rejouable sans doublon', function () {
    ($this->planifier)($this->jour, '2026-10-26', '2026-11-08');

    ($this->prolonger)();
    $avant = Vacation::query()->count();
    $resultat = ($this->prolonger)();

    expect($resultat['created'])->toBe(0)
        ->and(Vacation::query()->count())->toBe($avant);
});

it('avance d’un jour la nuit suivante', function () {
    ($this->planifier)($this->jour, '2026-10-26', '2026-11-08');
    ($this->prolonger)();

    Carbon::setTestNow('2026-11-03 00:20:00');
    ($this->prolonger)();

    expect(($this->dates)($this->jour)->last())->toBe('2026-12-15');
});

it('ne reconduit pas un remplaçant ponctuel à la place du titulaire', function () {
    ($this->planifier)($this->jour, '2026-10-26', '2026-11-08', repos: [1]);
    ($this->planifier)($this->nuit, '2026-10-26', '2026-10-26', attributs: ['est_remplacement' => true, 'heures_supplementaires' => true]);

    ($this->prolonger)();

    expect(Vacation::query()->where('agent_id', $this->nuit->id)->count())->toBe(1);
});

it('reconduit le titulaire absent et remplacé, pas son remplaçant', function () {
    ($this->planifier)($this->jour, '2026-10-26', '2026-11-08');

    $this->actingAs($this->admin)->postJson('/api/v1/absences', [
        'agent_id' => $this->jour->id,
        'type' => 'autre',
        'date_debut' => '2026-11-05',
        'date_fin' => '2026-11-05',
        'motif' => 'Absent',
        'statut' => 'approuvee',
    ])->assertCreated();

    $trou = Vacation::query()->where('agent_id', $this->jour->id)->where('statut', 'a_recouvrir')->sole();
    $this->actingAs($this->admin)
        ->postJson("/api/v1/vacations/{$trou->id}/recouvrir", ['agent_id' => $this->nuit->id])
        ->assertCreated();

    ($this->prolonger)();

    expect(($this->dates)($this->jour)->contains('2026-11-12'))->toBeTrue()
        ->and(Vacation::query()->where('agent_id', $this->nuit->id)->count())->toBe(1);
});

it('marque à recouvrir le jour où l’agent est en congé approuvé', function () {
    ($this->planifier)($this->jour, '2026-10-26', '2026-11-08');

    $this->actingAs($this->admin)->postJson('/api/v1/absences', [
        'agent_id' => $this->jour->id,
        'type' => 'conge',
        'date_debut' => '2026-11-20',
        'date_fin' => '2026-11-21',
        'motif' => 'Congé',
        'statut' => 'approuvee',
    ])->assertCreated();

    ($this->prolonger)();

    $v = Vacation::query()->where('agent_id', $this->jour->id)->whereDate('date_debut', '2026-11-20')->sole();

    expect($v->statut->value)->toBe('a_recouvrir')
        ->and($v->absence_id)->not->toBeNull()
        ->and(Vacation::query()->where('agent_id', $this->jour->id)->whereDate('date_debut', '2026-11-22')->sole()->statut->value)->toBe('planifiee');
});

it('reconduit un agent actuellement en congé sans changer son statut', function () {
    ($this->planifier)($this->jour, '2026-10-26', '2026-11-08');
    $this->jour->update(['statut' => StatutAgent::Conge]);

    ($this->prolonger)();

    expect(($this->dates)($this->jour)->last())->toBe('2026-12-14')
        ->and($this->jour->fresh()->statut)->toBe(StatutAgent::Conge);
});

it('s’arrête à la fin du contrat et reprend après son renouvellement', function () {
    ($this->planifier)($this->jour, '2026-10-26', '2026-11-08');
    Contrat::query()->where('agent_id', $this->jour->id)->update(['type' => 'cdd', 'date_fin' => '2026-11-15']);

    $resultat = ($this->prolonger)();

    expect(($this->dates)($this->jour)->last())->toBe('2026-11-15')
        ->and($resultat['skipped'])->toBeGreaterThan(0);

    Contrat::query()->where('agent_id', $this->jour->id)->update(['date_fin' => '2027-11-15']);
    ($this->prolonger)();

    expect(($this->dates)($this->jour)->last())->toBe('2026-12-14');
});

it('ne reconduit pas un poste désactivé, supprimé ou un client inactif', function () {
    ($this->planifier)($this->jour, '2026-10-26', '2026-11-08');
    $avant = Vacation::query()->count();

    $this->poste->update(['planning_continu' => false]);
    expect(($this->prolonger)()['created'])->toBe(0);
    $this->poste->update(['planning_continu' => true]);

    $this->client->update(['statut' => 'resilie']);
    expect(($this->prolonger)()['created'])->toBe(0);
    $this->client->update(['statut' => 'actif']);

    $this->poste->delete();
    expect(($this->prolonger)()['created'])->toBe(0)
        ->and(Vacation::query()->count())->toBe($avant);
});

it('ne reconduit pas une affectation ponctuelle de quelques jours', function () {
    ($this->planifier)($this->jour, '2026-11-02', '2026-11-04');

    expect(($this->prolonger)()['created'])->toBe(0);
});

it('ne relance pas seul un planning arrêté, sauf à la reprise demandée', function () {
    ($this->planifier)($this->jour, '2026-10-12', '2026-10-25');

    expect(($this->prolonger)()['created'])->toBe(0);

    ($this->prolonger)(reprendre: true);

    $dates = ($this->dates)($this->jour);
    expect($dates->contains('2026-11-02'))->toBeTrue()
        ->and($dates->contains('2026-10-30'))->toBeFalse()
        ->and($dates->last())->toBe('2026-12-14');
});

it('ne reprend pas un planning arrêté depuis plus d’un mois', function () {
    ($this->planifier)($this->jour, '2026-09-01', '2026-09-20');

    expect(($this->prolonger)(reprendre: true)['created'])->toBe(0);
});

it('s’arrête au plafond de temps et termine au passage suivant', function () {
    ($this->planifier)($this->jour, '2026-10-26', '2026-11-08');

    $resultat = app(ProlongerPlanningPostesAction::class)->execute(dureeMaxSecondes: 0);

    expect($resultat['postes_restants'])->toBe(1)
        ->and($resultat['created'])->toBe(0)
        ->and(($this->prolonger)()['postes_restants'])->toBe(0)
        ->and(($this->dates)($this->jour)->last())->toBe('2026-12-14');
});

it('relance les plannings arrêtés une seule fois, via la migration de reprise', function () {
    ($this->planifier)($this->jour, '2026-10-12', '2026-10-25');

    ob_start();
    (require database_path('migrations/2026_11_02_130000_relancer_plannings_arretes.php'))->up();
    $sortie = ob_get_clean();

    expect($sortie)->toContain('Reprise des plannings : 1 poste(s)')
        ->and(($this->dates)($this->jour)->last())->toBe('2026-12-14');
});

it('efface les semaines reconduites quand on replanifie le poste', function () {
    ($this->planifier)($this->jour, '2026-10-26', '2026-11-08');
    ($this->prolonger)();

    $this->actingAs($this->admin)->postJson('/api/v1/vacations/bulk', [
        'vacations' => collect(range(0, 13))->map(fn ($i) => [
            'agent_id' => $this->nuit->id,
            'site_id' => $this->site->id,
            'poste_id' => $this->poste->id,
            'date_debut' => Carbon::parse('2026-11-09')->addDays($i)->toDateString(),
            'date_fin' => Carbon::parse('2026-11-09')->addDays($i)->toDateString(),
            'heure_debut' => '06:30',
            'heure_fin' => '18:30',
        ])->all(),
        'replace' => ['poste_id' => $this->poste->id, 'date_debut' => '2026-11-09', 'date_fin' => '2026-11-22', 'scope' => 'poste'],
    ])->assertSuccessful();

    expect(($this->dates)($this->jour)->last())->toBe('2026-11-08');

    ($this->prolonger)();

    expect(($this->dates)($this->nuit)->last())->toBe('2026-12-14')
        ->and(($this->dates)($this->jour)->last())->toBe('2026-11-08');
});

it('ne supprime pas à la replanification les créneaux manuels après la période', function () {
    ($this->planifier)($this->jour, '2026-12-01', '2026-12-01');

    $this->actingAs($this->admin)->postJson('/api/v1/vacations/bulk', [
        'vacations' => [[
            'agent_id' => $this->nuit->id,
            'site_id' => $this->site->id,
            'poste_id' => $this->poste->id,
            'date_debut' => '2026-11-10',
            'date_fin' => '2026-11-10',
            'heure_debut' => '06:30',
            'heure_fin' => '18:30',
        ]],
        'replace' => ['poste_id' => $this->poste->id, 'date_debut' => '2026-11-10', 'date_fin' => '2026-11-10', 'scope' => 'poste'],
    ])->assertSuccessful();

    expect(($this->dates)($this->jour)->all())->toBe(['2026-12-01']);
});

it('expose l’option sur le poste et la commande quotidienne existe', function () {
    $this->actingAs($this->admin)->getJson("/api/v1/sites/{$this->site->id}/postes")
        ->assertOk()
        ->assertJsonPath('data.0.planning_continu', true);

    $this->actingAs($this->admin)->putJson("/api/v1/sites/{$this->site->id}/postes/{$this->poste->id}", ['planning_continu' => false])
        ->assertOk()
        ->assertJsonPath('data.planning_continu', false);

    $this->artisan('vacations:prolonger-planning')->assertSuccessful();
});
