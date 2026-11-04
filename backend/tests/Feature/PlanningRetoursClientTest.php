<?php

use App\Application\Operation\ProlongerPlanningPostesAction;
use App\Application\Reporting\GetPosteCoverageAction;
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

/**
 * Retours client du planning : enchaînement jour / nuit quand le binôme est
 * absent, planning enregistré qui reste tel quel, période non hebdomadaire,
 * reconduction sans replanification.
 */
beforeEach(function () {
    // Lundi 2 novembre 2026.
    Carbon::setTestNow('2026-11-02 08:00:00');
    $this->seed(RolePermissionSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('super-admin');

    $this->grade = Grade::query()->create(['libelle' => 'Agent', 'type_agent' => TypeAgent::Agent]);
    $client = Client::query()->create(['type' => 'entreprise', 'raison_sociale' => 'Client', 'statut' => 'actif']);
    $this->site = Site::query()->create([
        'client_id' => $client->id,
        'zone_id' => Zone::query()->create(['nom' => 'Zone'])->id,
        'nom' => 'Site',
    ]);

    $this->agent = fn (string $nom) => tap(Agent::query()->create([
        'grade_id' => $this->grade->id,
        'type' => TypeAgent::Agent,
        'nom' => $nom,
        'prenom' => 'Agent',
        'matricule' => 'R-'.$nom,
        'statut' => 'disponible',
    ]), function (Agent $a) {
        Contrat::query()->where('agent_id', $a->id)->forceDelete();
        Contrat::query()->create(['agent_id' => $a->id, 'type' => 'cdi', 'date_debut' => '2026-01-01', 'statut' => 'actif']);
    });

    // Poste jour et nuit tenu par un binôme.
    $this->poste = Poste::query()->create([
        'site_id' => $this->site->id,
        'nom' => 'Entrée',
        'agents_requis' => 2,
        'heure_debut' => '06:00',
        'heure_fin' => '18:00',
        'heure_debut_nuit' => '18:00',
        'heure_fin_nuit' => '06:00',
    ]);
    $this->jour = ($this->agent)('Jour');
    $this->nuit = ($this->agent)('Nuit');

    $this->poser = fn (Agent $a, string $date, string $debut, string $fin, array $attributs = []) => Vacation::query()->create([
        'agent_id' => $a->id,
        'site_id' => $this->site->id,
        'poste_id' => $this->poste->id,
        'date_debut' => $date,
        'date_fin' => $fin <= $debut ? Carbon::parse($date)->addDay()->toDateString() : $date,
        'heure_debut' => $debut,
        'heure_fin' => $fin,
        'statut' => 'planifiee',
        ...$attributs,
    ]);

    /** Le binôme sur une plage de dates. */
    $this->binome = function (string $du, string $au) {
        for ($d = Carbon::parse($du); $d->lte($au); $d->addDay()) {
            ($this->poser)($this->jour, $d->toDateString(), '06:00', '18:00');
            ($this->poser)($this->nuit, $d->toDateString(), '18:00', '06:00');
        }
    };

    $this->absence = fn (Agent $a, string $du, string $au) => $this->actingAs($this->admin)
        ->postJson('/api/v1/absences', [
            'agent_id' => $a->id,
            'type' => 'autre',
            'date_debut' => $du,
            'date_fin' => $au,
            'motif' => 'Test',
            'statut' => 'approuvee',
        ])->assertCreated();

    $this->planifier = fn (array $vacations, string $du, string $au) => $this->actingAs($this->admin)
        ->postJson('/api/v1/vacations/bulk', [
            'vacations' => $vacations,
            'replace' => ['poste_id' => $this->poste->id, 'date_debut' => $du, 'date_fin' => $au, 'scope' => 'poste'],
        ]);

    $this->ligne = fn (Agent $a, string $date, string $debut = '06:00', string $fin = '18:00') => [
        'agent_id' => $a->id,
        'site_id' => $this->site->id,
        'poste_id' => $this->poste->id,
        'date_debut' => $date,
        'date_fin' => $fin <= $debut ? Carbon::parse($date)->addDay()->toDateString() : $date,
        'heure_debut' => $debut,
        'heure_fin' => $fin,
    ];
});

afterEach(function () {
    Carbon::setTestNow();
});

/* ---------- 1. L’agent continue quand son binôme est absent ---------- */

it('laisse l’agent de jour continuer la nuit du binôme absent', function () {
    ($this->binome)('2026-11-09', '2026-11-11');
    ($this->absence)($this->nuit, '2026-11-10', '2026-11-10');
    $trou = Vacation::query()->where('agent_id', $this->nuit->id)->where('statut', 'a_recouvrir')->sole();

    $this->actingAs($this->admin)
        ->postJson("/api/v1/vacations/{$trou->id}/recouvrir", ['agent_id' => $this->jour->id])
        ->assertCreated()
        ->assertJsonPath('data.heures_supplementaires', true);
});

it('laisse l’agent de nuit continuer le jour du binôme absent', function () {
    ($this->binome)('2026-11-09', '2026-11-11');
    ($this->absence)($this->jour, '2026-11-10', '2026-11-10');
    $trou = Vacation::query()->where('agent_id', $this->jour->id)->where('statut', 'a_recouvrir')->sole();

    $this->actingAs($this->admin)
        ->postJson("/api/v1/vacations/{$trou->id}/recouvrir", ['agent_id' => $this->nuit->id])
        ->assertCreated()
        ->assertJsonPath('data.heures_supplementaires', true);
});

it('accepte l’enchaînement quand on change l’agent directement sur la vacation', function () {
    ($this->binome)('2026-11-09', '2026-11-11');
    $nuit = Vacation::query()->where('agent_id', $this->nuit->id)->whereDate('date_debut', '2026-11-10')->sole();

    $this->actingAs($this->admin)
        ->putJson("/api/v1/vacations/{$nuit->id}", ['agent_id' => $this->jour->id])
        ->assertOk()
        ->assertJsonPath('data.agent_id', $this->jour->id)
        ->assertJsonPath('data.heures_supplementaires', true);
});

it('accepte l’enchaînement quand on ajoute l’agent sur la case à recouvrir', function () {
    ($this->binome)('2026-11-09', '2026-11-11');
    ($this->absence)($this->nuit, '2026-11-10', '2026-11-10');

    $this->actingAs($this->admin)
        ->postJson('/api/v1/vacations', [...($this->ligne)($this->jour, '2026-11-10', '18:00', '06:00'), 'date_fin' => '2026-11-10'])
        ->assertCreated();

    expect(Vacation::query()->where('statut', 'a_recouvrir')->count())->toBe(0);
});

it('ne bloque plus la vacation normale qui suit un remplacement', function () {
    ($this->binome)('2026-11-09', '2026-11-10');
    ($this->absence)($this->nuit, '2026-11-10', '2026-11-10');
    $trou = Vacation::query()->where('agent_id', $this->nuit->id)->where('statut', 'a_recouvrir')->sole();
    $this->actingAs($this->admin)->postJson("/api/v1/vacations/{$trou->id}/recouvrir", ['agent_id' => $this->jour->id])->assertCreated();

    // Sa journée du lendemain démarre à la fin de la nuit couverte.
    $this->actingAs($this->admin)
        ->postJson('/api/v1/vacations', ($this->ligne)($this->jour, '2026-11-11'))
        ->assertCreated();
});

it('garde le repos minimum hors remplacement', function () {
    ($this->poser)($this->jour, '2026-11-09', '18:00', '06:00');

    $this->actingAs($this->admin)
        ->postJson('/api/v1/vacations', ($this->ligne)($this->jour, '2026-11-10'))
        ->assertUnprocessable();
});

it('laisse un seul agent tenir le jour et la nuit d’un poste à un agent', function () {
    $this->poste->update(['agents_requis' => 1]);

    $this->actingAs($this->admin)->postJson('/api/v1/vacations', ($this->ligne)($this->jour, '2026-11-10'))->assertCreated();
    $this->actingAs($this->admin)->postJson('/api/v1/vacations', [...($this->ligne)($this->jour, '2026-11-10', '18:00', '06:00'), 'date_fin' => '2026-11-10'])->assertCreated();

    $couverture = collect(app(GetPosteCoverageAction::class)->execute('2026-11-10', $this->site->id))->firstWhere('poste_id', $this->poste->id);
    expect($couverture['statut'])->toBe('ok')->and($couverture['planifies'])->toBe(1);
});

it('compte un poste couvert et sans sur-effectif quand l’agent de jour prend aussi la nuit', function () {
    ($this->binome)('2026-11-10', '2026-11-10');
    ($this->absence)($this->nuit, '2026-11-10', '2026-11-10');
    $trou = Vacation::query()->where('statut', 'a_recouvrir')->sole();
    $this->actingAs($this->admin)->postJson("/api/v1/vacations/{$trou->id}/recouvrir", ['agent_id' => $this->jour->id])->assertCreated();

    $couverture = collect(app(GetPosteCoverageAction::class)->execute('2026-11-10', $this->site->id))->firstWhere('poste_id', $this->poste->id);
    expect($couverture['statut'])->toBe('ok')->and($couverture['manquant'])->toBe(0);
});

it('reconduit le titulaire et non l’agent mis à sa place pour un jour', function () {
    ($this->binome)('2026-10-26', '2026-11-08');
    $nuit = Vacation::query()->where('agent_id', $this->nuit->id)->whereDate('date_debut', '2026-11-08')->sole();
    $this->actingAs($this->admin)->putJson("/api/v1/vacations/{$nuit->id}", ['agent_id' => $this->jour->id])->assertOk();

    app(ProlongerPlanningPostesAction::class)->execute();

    // Le dimanche 15 reprend le titulaire de nuit, pas le remplaçant du 8.
    foreach (['2026-11-15', '2026-11-22', '2026-11-29'] as $dimanche) {
        expect(Vacation::query()->whereDate('date_debut', $dimanche)->where('heure_debut', 'like', '18:00%')->sole()->agent_id)
            ->toBe($this->nuit->id);
    }
});

it('reconduit le titulaire d’une case marquée à la main et recouverte', function () {
    ($this->binome)('2026-10-26', '2026-11-08');
    $nuit = Vacation::query()->where('agent_id', $this->nuit->id)->whereDate('date_debut', '2026-11-08')->sole();
    $this->actingAs($this->admin)->putJson("/api/v1/vacations/{$nuit->id}", ['statut' => 'a_recouvrir'])->assertOk();
    $this->actingAs($this->admin)->postJson("/api/v1/vacations/{$nuit->id}/recouvrir", ['agent_id' => $this->jour->id])->assertCreated();

    app(ProlongerPlanningPostesAction::class)->execute();

    foreach (['2026-11-15', '2026-11-22', '2026-11-29'] as $dimanche) {
        expect(Vacation::query()->whereDate('date_debut', $dimanche)->where('heure_debut', 'like', '18:00%')->sole()->agent_id)
            ->toBe($this->nuit->id);
    }
});

it('ne refuse plus la nuit qui finit le matin du premier jour d’absence', function () {
    ($this->absence)($this->nuit, '2026-11-11', '2026-11-11');

    $this->actingAs($this->admin)
        ->postJson('/api/v1/vacations', [...($this->ligne)($this->nuit, '2026-11-10', '18:00', '06:00'), 'date_fin' => '2026-11-10'])
        ->assertCreated();
});

/* ---------- 2. Ce qui est enregistré reste tel quel ---------- */

it('rend tout le planning du poste quand on demande tout', function () {
    ($this->binome)('2026-11-09', '2026-11-28');

    $this->actingAs($this->admin)
        ->getJson('/api/v1/vacations?poste_id='.$this->poste->id.'&all=1')
        ->assertOk()
        ->assertJsonCount(40, 'data');
});

it('expose de quoi distinguer titulaire, remplaçant et reconduction', function () {
    ($this->poser)($this->jour, '2026-11-10', '06:00', '18:00', ['est_remplacement' => true, 'reconduite' => true]);

    $this->actingAs($this->admin)
        ->getJson('/api/v1/vacations?poste_id='.$this->poste->id.'&all=1')
        ->assertJsonPath('data.0.est_remplacement', true)
        ->assertJsonPath('data.0.reconduite', true);
});

it('mémorise la période enregistrée sur le poste', function () {
    ($this->planifier)([($this->ligne)($this->jour, '2026-11-09')], '2026-11-09', '2026-11-20')->assertCreated();

    $this->actingAs($this->admin)
        ->getJson('/api/v1/postes?all=1&site_id='.$this->site->id)
        ->assertJsonPath('data.0.planning_date_debut', '2026-11-09')
        ->assertJsonPath('data.0.planning_date_fin', '2026-11-20');
});

it('ne touche ni au passé ni aux vacations terminées en replanifiant', function () {
    $passe = ($this->poser)($this->jour, '2026-10-30', '06:00', '18:00');
    $terminee = ($this->poser)($this->jour, '2026-10-31', '06:00', '18:00', ['statut' => 'terminee']);

    ($this->planifier)([($this->ligne)($this->nuit, '2026-11-05')], '2026-10-29', '2026-11-20')->assertCreated();

    expect(Vacation::query()->whereKey([$passe->id, $terminee->id])->count())->toBe(2);
});

it('garde le remplacement déjà saisi en replanifiant', function () {
    ($this->binome)('2026-11-09', '2026-11-11');
    ($this->absence)($this->nuit, '2026-11-10', '2026-11-10');
    $trou = Vacation::query()->where('agent_id', $this->nuit->id)->where('statut', 'a_recouvrir')->sole();
    $remplacant = ($this->agent)('Remplacant');
    $this->actingAs($this->admin)->postJson("/api/v1/vacations/{$trou->id}/recouvrir", ['agent_id' => $remplacant->id])->assertCreated();

    // Le planificateur réenregistre le même planning : le remplaçant reste.
    ($this->planifier)([
        ($this->ligne)($this->jour, '2026-11-10'),
        ($this->ligne)($this->nuit, '2026-11-10', '18:00', '06:00'),
    ], '2026-11-09', '2026-11-11')->assertCreated();

    expect(Vacation::query()->where('agent_id', $remplacant->id)->where('statut', 'planifiee')->count())->toBe(1)
        ->and(Vacation::query()->where('agent_id', $this->nuit->id)->where('statut', 'planifiee')->count())->toBe(0);
});

it('garde en replanification un titulaire au statut congé, sans absence ce jour-là', function () {
    $this->nuit->update(['statut' => 'conge']);

    ($this->planifier)([($this->ligne)($this->nuit, '2026-11-20', '18:00', '06:00')], '2026-11-09', '2026-11-30')->assertCreated();
});

it('marque à recouvrir au lieu de tout refuser quand un agent du planning est absent', function () {
    ($this->absence)($this->nuit, '2026-11-10', '2026-11-10');

    ($this->planifier)([
        ($this->ligne)($this->jour, '2026-11-10'),
        ($this->ligne)($this->nuit, '2026-11-10', '18:00', '06:00'),
        ($this->ligne)($this->nuit, '2026-11-11', '18:00', '06:00'),
    ], '2026-11-09', '2026-11-12')->assertCreated();

    expect(Vacation::query()->where('agent_id', $this->nuit->id)->whereDate('date_debut', '2026-11-10')->sole()->statut->value)->toBe('a_recouvrir')
        ->and(Vacation::query()->where('agent_id', $this->nuit->id)->whereDate('date_debut', '2026-11-11')->sole()->statut->value)->toBe('planifiee');
});

/* ---------- 3 et 4. Un planning sur la période, qui se poursuit seul ---------- */

it('enregistre une période non hebdomadaire jour par jour, à l’identique', function () {
    // Deux jours, deux nuits, deux repos : le programme change d’une semaine à l’autre.
    $lignes = [];
    for ($i = 0; $i < 30; $i++) {
        $date = Carbon::parse('2026-11-09')->addDays($i)->toDateString();
        match ($i % 6) {
            0, 1 => $lignes[] = ($this->ligne)($this->jour, $date),
            2, 3 => $lignes[] = ($this->ligne)($this->jour, $date, '18:00', '06:00'),
            default => null,
        };
    }

    ($this->planifier)($lignes, '2026-11-09', '2026-12-08')->assertCreated();

    expect(Vacation::query()->where('agent_id', $this->jour->id)->count())->toBe(count($lignes))
        ->and(Vacation::query()->whereDate('date_debut', '2026-11-16')->sole()->heure_debut)->toStartWith('06:00')
        ->and(Vacation::query()->whereDate('date_debut', '2026-11-23')->sole()->heure_debut)->toStartWith('18:00');
});

it('poursuit un rythme qui ne tombe pas sur la semaine', function () {
    // 2 jours travaillés, 1 de repos, sur 3 semaines.
    for ($i = 0; $i < 21; $i++) {
        if ($i % 3 !== 2) {
            ($this->poser)($this->jour, Carbon::parse('2026-10-19')->addDays($i)->toDateString(), '06:00', '18:00');
        }
    }

    app(ProlongerPlanningPostesAction::class)->execute();

    // Repos tous les 3 jours sans interruption après le 8 novembre.
    for ($i = 21; $i < 42; $i++) {
        $date = Carbon::parse('2026-10-19')->addDays($i)->toDateString();
        expect(Vacation::query()->whereDate('date_debut', $date)->count())->toBe($i % 3 === 2 ? 0 : 1, $date);
    }
});

it('ne prend pas deux semaines d’alternance pour un rythme quotidien', function () {
    for ($i = 0; $i < 14; $i++) {
        ($this->poser)($i < 7 ? $this->jour : $this->nuit, Carbon::parse('2026-10-26')->addDays($i)->toDateString(), '06:00', '18:00');
    }

    app(ProlongerPlanningPostesAction::class)->execute();

    expect(Vacation::query()->whereDate('date_debut', '2026-11-09')->sole()->agent_id)->toBe($this->jour->id)
        ->and(Vacation::query()->whereDate('date_debut', '2026-11-16')->sole()->agent_id)->toBe($this->nuit->id);
});

it('garde les créneaux de l’agent hors de la grille tant qu’on ne les retire pas', function () {
    $releve = ($this->agent)('Releve');
    ($this->planifier)([
        ($this->ligne)($this->jour, '2026-11-03'),
        ($this->ligne)($releve, '2026-11-04'),
    ], '2026-11-02', '2026-11-29')->assertCreated();

    $enregistrer = fn (array $conserver) => $this->actingAs($this->admin)->postJson('/api/v1/vacations/bulk', [
        'vacations' => [($this->ligne)($this->jour, '2026-11-03')],
        'replace' => [
            'poste_id' => $this->poste->id,
            'date_debut' => '2026-11-02',
            'date_fin' => '2026-11-29',
            'scope' => 'poste',
            'conserver_agent_ids' => $conserver,
        ],
    ])->assertCreated();

    $enregistrer([$releve->id]);
    expect(Vacation::query()->where('agent_id', $releve->id)->count())->toBe(1)
        ->and(Vacation::query()->where('agent_id', $this->jour->id)->count())->toBe(1);

    $enregistrer([]);
    expect(Vacation::query()->where('agent_id', $releve->id)->count())->toBe(0);
});
