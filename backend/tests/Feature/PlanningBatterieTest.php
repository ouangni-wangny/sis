<?php

use App\Application\Operation\ProlongerPlanningPostesAction;
use App\Application\Reporting\GetPosteCoverageAction;
use App\Domain\Shared\Enums\TypeAgent;
use App\Domain\Shared\Support\ShiftSchedule;
use App\Models\Absence;
use App\Models\Agent;
use App\Models\Client;
use App\Models\Contrat;
use App\Models\Controle;
use App\Models\Grade;
use App\Models\Poste;
use App\Models\Site;
use App\Models\User;
use App\Models\Vacation;
use App\Models\Zone;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;

/**
 * Batterie du module planning : règles horaires, effectif, absences,
 * remplacements, reconduction et invariants sur des opérations aléatoires.
 */
beforeEach(function () {
    // Lundi 2 novembre 2026.
    Carbon::setTestNow('2026-11-02 08:00:00');
    $this->seed(RolePermissionSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('super-admin');

    $this->grade = Grade::query()->create(['libelle' => 'Agent', 'type_agent' => TypeAgent::Agent]);
    $this->client = Client::query()->create(['type' => 'entreprise', 'raison_sociale' => 'Client', 'statut' => 'actif']);
    $this->site = Site::query()->create([
        'client_id' => $this->client->id,
        'zone_id' => Zone::query()->create(['nom' => 'Zone'])->id,
        'nom' => 'Site',
    ]);

    $this->agent = fn (string $nom) => tap(Agent::query()->create([
        'grade_id' => $this->grade->id,
        'type' => TypeAgent::Agent,
        'nom' => $nom,
        'prenom' => 'Agent',
        'matricule' => 'B-'.$nom,
        'statut' => 'disponible',
    ]), function (Agent $a) {
        Contrat::query()->where('agent_id', $a->id)->forceDelete();
        Contrat::query()->create(['agent_id' => $a->id, 'type' => 'cdi', 'date_debut' => '2026-01-01', 'statut' => 'actif']);
    });

    $this->poste = fn (array $attributs = []) => Poste::query()->create([
        'site_id' => $this->site->id,
        'nom' => 'Poste '.uniqid(),
        'agents_requis' => 5,
        'heure_debut' => '06:00',
        'heure_fin' => '18:00',
        ...$attributs,
    ]);

    $this->creer = fn (Agent $a, Poste $p, string $date, string $debut, string $fin, array $extra = []) => $this
        ->actingAs($this->admin)
        ->postJson('/api/v1/vacations', [
            'agent_id' => $a->id,
            'site_id' => $this->site->id,
            'poste_id' => $p->id,
            'date_debut' => $date,
            'date_fin' => $date,
            'heure_debut' => $debut,
            'heure_fin' => $fin,
            ...$extra,
        ]);

    /** Écrit directement, sans passer par les règles : état de départ d’un test. */
    $this->poser = function (Agent $a, Poste $p, string $date, string $debut, string $fin, array $attributs = []) {
        return Vacation::query()->create([
            'agent_id' => $a->id,
            'site_id' => $p->site_id,
            'poste_id' => $p->id,
            'date_debut' => $date,
            'date_fin' => $fin <= $debut ? Carbon::parse($date)->addDay()->toDateString() : $date,
            'heure_debut' => $debut,
            'heure_fin' => $fin,
            'statut' => 'planifiee',
            ...$attributs,
        ]);
    };

    $this->semaine = function (Agent $a, Poste $p, string $lundi, string $debut = '06:00', string $fin = '18:00') {
        for ($i = 0; $i < 7; $i++) {
            ($this->poser)($a, $p, Carbon::parse($lundi)->addDays($i)->toDateString(), $debut, $fin);
        }
    };

    $this->absence = fn (Agent $a, string $du, string $au, string $statut = 'approuvee', string $type = 'autre') => $this
        ->actingAs($this->admin)
        ->postJson('/api/v1/absences', [
            'agent_id' => $a->id,
            'type' => $type,
            'date_debut' => $du,
            'date_fin' => $au,
            'motif' => 'Test',
            'statut' => $statut,
        ]);

    $this->jours = fn (Agent $a) => Vacation::query()
        ->where('agent_id', $a->id)
        ->where('statut', '!=', 'annulee')
        ->orderBy('date_debut')
        ->get()
        ->map(fn (Vacation $v) => $v->date_debut->toDateString())
        ->all();

    $this->prolonger = fn (bool $reprendre = false) => app(ProlongerPlanningPostesAction::class)->execute(reprendre: $reprendre);

    $this->couverture = fn (Poste $p, string $date) => collect(app(GetPosteCoverageAction::class)->execute($date, $p->site_id))
        ->firstWhere('poste_id', $p->id);
});

afterEach(function () {
    Carbon::setTestNow();
});

/* ---------- Règles horaires d’un agent ---------- */

it('crée une affectation continue quand aucune date de fin n’est donnée', function () {
    $id = ($this->creer)(($this->agent)('A'), ($this->poste)(), '2026-11-10', '06:00', '18:00', ['date_fin' => null])
        ->assertCreated()
        ->json('data.id');

    expect(Vacation::query()->find($id)->date_fin)->toBeNull();
});

it('applique la règle horaire entre deux créneaux du même agent', function (string $a1, string $a2, string $b1, string $b2, int $decalage, int $statut) {
    $agent = ($this->agent)('A');
    $poste = ($this->poste)();
    $autre = ($this->poste)();

    ($this->poser)($agent, $poste, '2026-11-10', $a1, $a2);

    ($this->creer)($agent, $autre, Carbon::parse('2026-11-10')->addDays($decalage)->toDateString(), $b1, $b2)
        ->assertStatus($statut);
})->with([
    'chevauchement le même jour' => ['06:00', '18:00', '12:00', '20:00', 0, 409],
    'même créneau' => ['06:00', '18:00', '06:00', '18:00', 0, 409],
    'nuit qui mord sur le matin' => ['20:00', '08:00', '07:00', '12:00', 1, 409],
    'repos de 10 h 59 refusé' => ['06:00', '12:00', '22:59', '23:30', 0, 422],
    'repos de 11 h pile accepté' => ['06:00', '12:00', '23:00', '23:30', 0, 201],
    'repos de 11 h après une nuit' => ['20:00', '06:00', '17:00', '23:00', 1, 201],
    'repos insuffisant après une nuit' => ['20:00', '06:00', '16:00', '23:00', 1, 422],
    'jour suivant avec repos suffisant' => ['06:00', '18:00', '06:00', '18:00', 1, 201],
    'nuit puis nuit suivante' => ['18:00', '06:00', '18:00', '06:00', 1, 201],
    'autre semaine sans lien' => ['06:00', '18:00', '20:00', '23:00', 7, 201],
]);

it('calcule la fin de vacation selon le type de créneau', function (string $debut, string $fin, string $dateFin) {
    $id = ($this->creer)(($this->agent)('A'), ($this->poste)(), '2026-11-10', $debut, $fin)
        ->assertCreated()
        ->json('data.id');

    expect(Vacation::query()->find($id)->date_fin->toDateString())->toBe($dateFin);
})->with([
    'jour' => ['06:00', '18:00', '2026-11-10'],
    'nuit' => ['18:00', '06:00', '2026-11-11'],
    'fin à minuit' => ['14:00', '00:00', '2026-11-11'],
]);

it('refuse un créneau trop long et accepte le maximum', function (string $debut, string $fin, int $statut) {
    ($this->creer)(($this->agent)('A'), ($this->poste)(), '2026-11-10', $debut, $fin)->assertStatus($statut);
})->with([
    '12 h' => ['06:00', '18:00', 201],
    '12 h 01' => ['06:00', '18:01', 422],
    'nuit de 12 h' => ['18:00', '06:00', 201],
    'nuit de 13 h' => ['18:00', '07:00', 422],
]);

it('accepte un cycle de 24 h seulement quand il est continu', function () {
    $poste = ($this->poste)(['heure_debut' => '07:00', 'heure_fin' => '07:00']);

    $id = ($this->creer)(($this->agent)('A'), $poste, '2026-11-10', '07:00', '07:00')->assertCreated()->json('data.id');

    expect(Vacation::query()->find($id)->date_fin->toDateString())->toBe('2026-11-11');
});

it('refuse un agent sans contrat, archivé ou en absence approuvée', function () {
    $poste = ($this->poste)();

    $sansContrat = ($this->agent)('SansContrat');
    Contrat::query()->where('agent_id', $sansContrat->id)->update(['statut' => 'termine', 'date_fin' => '2026-10-01']);
    ($this->creer)($sansContrat, $poste, '2026-11-10', '06:00', '18:00')->assertStatus(422);

    $archive = ($this->agent)('Archive');
    $archive->update(['statut' => 'archive']);
    ($this->creer)($archive, $poste, '2026-11-10', '06:00', '18:00')->assertStatus(422);

    $absent = ($this->agent)('Absent');
    ($this->absence)($absent, '2026-11-09', '2026-11-11')->assertCreated();
    expect(($this->creer)($absent, $poste, '2026-11-10', '06:00', '18:00')->status())->toBeGreaterThanOrEqual(400);
});

it('refuse un contrat terminé avant la date et accepte une date avant sa fin', function () {
    $poste = ($this->poste)();
    $agent = ($this->agent)('Cdd');
    Contrat::query()->where('agent_id', $agent->id)->update(['type' => 'cdd', 'date_fin' => '2026-11-15']);

    ($this->creer)($agent, $poste, '2026-11-15', '06:00', '18:00')->assertCreated();
    ($this->creer)($agent, $poste, '2026-11-16', '06:00', '18:00')->assertStatus(422);
});

/* ---------- Effectif du poste ---------- */

it('n’accepte jamais plus d’agents que l’effectif sur un poste simple', function (int $requis) {
    $poste = ($this->poste)(['agents_requis' => $requis]);

    for ($i = 1; $i <= $requis; $i++) {
        ($this->creer)(($this->agent)("A{$i}"), $poste, '2026-11-10', '06:00', '18:00')->assertCreated();
    }
    ($this->creer)(($this->agent)('Trop'), $poste, '2026-11-10', '06:00', '18:00')->assertStatus(422);

    expect(($this->couverture)($poste, '2026-11-10')['statut'])->toBe('ok');
})->with([1, 2, 3]);

it('n’accepte jamais de sur-effectif sur un poste jour et nuit', function (int $requis) {
    $poste = ($this->poste)([
        'agents_requis' => $requis,
        'heure_debut_nuit' => '18:00',
        'heure_fin_nuit' => '06:00',
    ]);

    $n = 0;
    foreach ([['06:00', '18:00'], ['18:00', '06:00']] as [$debut, $fin]) {
        for ($i = 0; $i < 4; $i++) {
            $n++;
            ($this->creer)(($this->agent)("A{$n}"), $poste, '2026-11-10', $debut, $fin);
        }
    }

    $c = ($this->couverture)($poste, '2026-11-10');

    expect($c['statut'])->not->toBe('sur_effectif')
        ->and($c['planifies'])->toBeLessThanOrEqual($requis);
})->with([1, 2, 3, 4, 5]);

it('couvre un poste jour et nuit à 2 agents : sous-effectif puis complet', function () {
    $poste = ($this->poste)(['agents_requis' => 2, 'heure_debut_nuit' => '18:00', 'heure_fin_nuit' => '06:00']);

    expect(($this->couverture)($poste, '2026-11-10')['statut'])->toBe('non_couvert');

    ($this->creer)(($this->agent)('J'), $poste, '2026-11-10', '06:00', '18:00')->assertCreated();
    expect(($this->couverture)($poste, '2026-11-10')['statut'])->toBe('sous_effectif');

    ($this->creer)(($this->agent)('N'), $poste, '2026-11-10', '18:00', '06:00')->assertCreated();
    $c = ($this->couverture)($poste, '2026-11-10');

    expect($c['statut'])->toBe('ok')
        ->and(($this->couverture)($poste, '2026-11-11')['statut'])->toBe('non_couvert');
});

it('libère la place du poste quand le titulaire est absent ou la vacation annulée', function () {
    $poste = ($this->poste)(['agents_requis' => 1]);
    $a = ($this->agent)('A');
    $v = ($this->poser)($a, $poste, '2026-11-10', '06:00', '18:00');

    ($this->absence)($a, '2026-11-10', '2026-11-10')->assertCreated();
    expect($v->fresh()->statut->value)->toBe('a_recouvrir')
        ->and(($this->couverture)($poste, '2026-11-10')['statut'])->toBe('non_couvert');

    ($this->creer)(($this->agent)('B'), $poste, '2026-11-10', '06:00', '18:00')->assertCreated();
});

/* ---------- Absences ---------- */

it('ne marque que les jours de l’absence', function () {
    $poste = ($this->poste)();
    $a = ($this->agent)('A');
    ($this->semaine)($a, $poste, '2026-11-09');

    ($this->absence)($a, '2026-11-11', '2026-11-12')->assertCreated();

    $trous = Vacation::query()->where('statut', 'a_recouvrir')->orderBy('date_debut')->get()
        ->map(fn ($v) => $v->date_debut->toDateString())->all();

    expect($trous)->toBe(['2026-11-11', '2026-11-12']);
});

it('découpe une vacation sur période et garde les jours hors absence, sans chevauchement', function () {
    $poste = ($this->poste)();
    $a = ($this->agent)('A');
    Vacation::query()->create([
        'agent_id' => $a->id,
        'site_id' => $this->site->id,
        'poste_id' => $poste->id,
        'date_debut' => '2026-11-09',
        'date_fin' => '2026-11-20',
        'heure_debut' => '06:00',
        'heure_fin' => '18:00',
        'statut' => 'planifiee',
    ]);

    ($this->absence)($a, '2026-11-12', '2026-11-13')->assertCreated();

    $jours = [];
    foreach (Vacation::query()->where('agent_id', $a->id)->get() as $v) {
        foreach (ShiftSchedule::fromVacation($v)->days() as $d) {
            $jours[] = $d.' '.$v->statut->value;
        }
    }
    sort($jours);

    expect($jours)->toHaveCount(12)
        ->and($jours)->toContain('2026-11-11 planifiee', '2026-11-12 a_recouvrir', '2026-11-13 a_recouvrir', '2026-11-14 planifiee');
});

it('découpe une affectation sans fin sans perdre la suite', function () {
    $poste = ($this->poste)();
    $a = ($this->agent)('A');
    Vacation::query()->create([
        'agent_id' => $a->id,
        'site_id' => $this->site->id,
        'poste_id' => $poste->id,
        'date_debut' => '2026-11-09',
        'date_fin' => null,
        'heure_debut' => '06:00',
        'heure_fin' => '18:00',
        'statut' => 'planifiee',
    ]);

    ($this->absence)($a, '2026-11-12', '2026-11-12')->assertCreated();

    $apres = Vacation::query()->where('agent_id', $a->id)->whereDate('date_debut', '2026-11-13')->sole();

    expect($apres->date_fin)->toBeNull()
        ->and(Vacation::query()->where('statut', 'a_recouvrir')->sole()->date_debut->toDateString())->toBe('2026-11-12');
});

it('garde la nuit de la veille et marque celle du premier jour d’absence', function () {
    $poste = ($this->poste)();
    $a = ($this->agent)('A');
    $veille = ($this->poser)($a, $poste, '2026-11-09', '18:00', '06:00');
    $jour = ($this->poser)($a, $poste, '2026-11-10', '18:00', '06:00');

    ($this->absence)($a, '2026-11-10', '2026-11-10')->assertCreated();

    expect($veille->fresh()->statut->value)->toBe('planifiee')
        ->and($jour->fresh()->statut->value)->toBe('a_recouvrir');
});

it('n’agit pas tant que l’absence n’est pas approuvée, puis agit à l’approbation', function () {
    $poste = ($this->poste)();
    $a = ($this->agent)('A');
    $v = ($this->poser)($a, $poste, '2026-11-10', '06:00', '18:00');

    $id = ($this->absence)($a, '2026-11-10', '2026-11-10', 'en_attente')->assertCreated()->json('data.id');
    expect($v->fresh()->statut->value)->toBe('planifiee');

    $this->actingAs($this->admin)->putJson("/api/v1/absences/{$id}", ['statut' => 'approuvee'])->assertOk();
    expect($v->fresh()->statut->value)->toBe('a_recouvrir');
});

it('rétablit la vacation si l’absence est annulée', function () {
    $poste = ($this->poste)();
    $a = ($this->agent)('A');
    $v = ($this->poser)($a, $poste, '2026-11-10', '06:00', '18:00');

    $id = ($this->absence)($a, '2026-11-10', '2026-11-10')->assertCreated()->json('data.id');
    $this->actingAs($this->admin)->putJson("/api/v1/absences/{$id}", ['statut' => 'annulee'])->assertOk();

    expect($v->fresh()->statut->value)->toBe('planifiee')
        ->and($v->fresh()->absence_id)->toBeNull();
});

it('déplace les trous quand les dates de l’absence changent', function () {
    $poste = ($this->poste)();
    $a = ($this->agent)('A');
    ($this->semaine)($a, $poste, '2026-11-09');

    $id = ($this->absence)($a, '2026-11-10', '2026-11-10')->assertCreated()->json('data.id');
    $this->actingAs($this->admin)->putJson("/api/v1/absences/{$id}", ['date_debut' => '2026-11-12', 'date_fin' => '2026-11-12'])->assertOk();

    expect(Vacation::query()->where('statut', 'a_recouvrir')->sole()->date_debut->toDateString())->toBe('2026-11-12');
});

/* ---------- Remplacements ---------- */

it('garde un poste à l’effectif exact après remplacement', function () {
    $poste = ($this->poste)(['agents_requis' => 1]);
    $a = ($this->agent)('A');
    $trou = ($this->poser)($a, $poste, '2026-11-10', '06:00', '18:00');
    ($this->absence)($a, '2026-11-10', '2026-11-10')->assertCreated();

    $this->actingAs($this->admin)
        ->postJson("/api/v1/vacations/{$trou->id}/recouvrir", ['agent_id' => ($this->agent)('B')->id])
        ->assertCreated();

    expect(($this->couverture)($poste, '2026-11-10')['statut'])->toBe('ok');
});

it('permet de remplacer un remplaçant lui-même absent', function () {
    $poste = ($this->poste)(['agents_requis' => 1]);
    $a = ($this->agent)('A');
    $b = ($this->agent)('B');
    $c = ($this->agent)('C');
    $trou = ($this->poser)($a, $poste, '2026-11-10', '06:00', '18:00');
    ($this->absence)($a, '2026-11-10', '2026-11-10')->assertCreated();

    $this->actingAs($this->admin)->postJson("/api/v1/vacations/{$trou->id}/recouvrir", ['agent_id' => $b->id])->assertCreated();
    ($this->absence)($b, '2026-11-10', '2026-11-10')->assertCreated();

    $trou2 = Vacation::query()->where('agent_id', $b->id)->where('statut', 'a_recouvrir')->sole();
    $this->actingAs($this->admin)->postJson("/api/v1/vacations/{$trou2->id}/recouvrir", ['agent_id' => $c->id])->assertCreated();

    expect(($this->couverture)($poste, '2026-11-10')['statut'])->toBe('ok');
});

it('laisse faire évoluer la vacation de l’agent qui a enchaîné en remplacement', function () {
    $poste = ($this->poste)(['agents_requis' => 2, 'heure_debut_nuit' => '19:00', 'heure_fin_nuit' => '07:00']);
    $descend = ($this->agent)('Descend');
    $absent = ($this->agent)('Absent');
    ($this->poser)($descend, $poste, '2026-11-10', '06:00', '18:00');
    $trou = ($this->poser)($absent, $poste, '2026-11-10', '19:00', '07:00');
    ($this->absence)($absent, '2026-11-10', '2026-11-10')->assertCreated();

    $id = $this->actingAs($this->admin)
        ->postJson("/api/v1/vacations/{$trou->id}/recouvrir", ['agent_id' => $descend->id])
        ->assertCreated()
        ->assertJsonPath('data.heures_supplementaires', true)
        ->json('data.id');

    // Sa vacation enchaîne sans repos légal : la faire démarrer ne doit pas être refusé.
    $this->actingAs($this->admin)->putJson("/api/v1/vacations/{$id}", ['statut' => 'en_cours'])
        ->assertOk()
        ->assertJsonPath('data.statut', 'en_cours');
});

it('ne compte pas l’agent qui descend comme disponible s’il finit après le début du créneau de plus d’une heure', function () {
    $poste = ($this->poste)();
    $absent = ($this->agent)('Absent');
    $tard = ($this->agent)('Tard');
    ($this->poser)($tard, $poste, '2026-11-10', '08:00', '20:00');
    $trou = ($this->poser)($absent, $poste, '2026-11-10', '18:00', '06:00', ['statut' => 'a_recouvrir']);

    $this->actingAs($this->admin)->getJson("/api/v1/vacations/{$trou->id}/agents-descendants")
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

/* ---------- Reconduction ---------- */

it('poursuit l’alternance même planifiée sur un nombre impair de semaines', function () {
    $poste = ($this->poste)();
    $a = ($this->agent)('A');
    $b = ($this->agent)('B');
    // 3 semaines : A, B, A. La suite logique est B.
    ($this->semaine)($a, $poste, '2026-10-19');
    ($this->semaine)($b, $poste, '2026-10-26');
    ($this->semaine)($a, $poste, '2026-11-02');

    ($this->prolonger)();

    expect(($this->jours)($b))->toContain('2026-11-09')
        ->and(($this->jours)($a))->not->toContain('2026-11-09')
        ->and(($this->jours)($a))->toContain('2026-11-16')
        ->and(($this->jours)($b))->not->toContain('2026-11-16');
});

it('poursuit une rotation de 3 agents', function () {
    $poste = ($this->poste)();
    [$a, $b, $c] = [($this->agent)('A'), ($this->agent)('B'), ($this->agent)('C')];
    foreach ([['2026-10-12', $a], ['2026-10-19', $b], ['2026-10-26', $c], ['2026-11-02', $a]] as [$lundi, $qui]) {
        ($this->semaine)($qui, $poste, $lundi);
    }

    ($this->prolonger)();

    expect(($this->jours)($b))->toContain('2026-11-09')
        ->and(($this->jours)($c))->toContain('2026-11-16')
        ->and(($this->jours)($a))->toContain('2026-11-23');
});

it('reconduit un rythme un jour sur deux en gardant l’ordre', function () {
    $poste = ($this->poste)();
    $a = ($this->agent)('A');
    $b = ($this->agent)('B');
    for ($d = Carbon::parse('2026-10-26'); $d->lte('2026-11-08'); $d->addDay()) {
        ($this->poser)($d->dayOfYear % 2 ? $a : $b, $poste, $d->toDateString(), '06:00', '18:00');
    }

    expect(($this->prolonger)()['skipped'])->toBe(0);

    $parJour = Vacation::query()->whereDate('date_debut', '>', '2026-11-08')->get()->groupBy(fn ($v) => $v->date_debut->toDateString());

    expect($parJour->every(fn ($l) => $l->count() === 1))->toBeTrue();
});

it('reconduit un poste jour et nuit sans jamais de sur-effectif', function () {
    $poste = ($this->poste)(['agents_requis' => 2, 'heure_debut_nuit' => '18:00', 'heure_fin_nuit' => '06:00']);
    ($this->semaine)(($this->agent)('J'), $poste, '2026-10-26');
    ($this->semaine)(($this->agent)('N'), $poste, '2026-10-26', '18:00', '06:00');
    ($this->semaine)(Agent::query()->where('nom', 'J')->first(), $poste, '2026-11-02');
    ($this->semaine)(Agent::query()->where('nom', 'N')->first(), $poste, '2026-11-02', '18:00', '06:00');

    expect(($this->prolonger)()['skipped'])->toBe(0);

    foreach (['2026-11-09', '2026-11-20', '2026-12-14'] as $d) {
        expect(($this->couverture)($poste, $d)['statut'])->toBe('ok');
    }
});

it('reconduit un cycle de 24 h un jour sur deux entre deux agents', function () {
    $poste = ($this->poste)(['agents_requis' => 1, 'heure_debut' => '07:00', 'heure_fin' => '07:00']);
    $a = ($this->agent)('A');
    $b = ($this->agent)('B');
    for ($d = Carbon::parse('2026-10-26'); $d->lte('2026-11-08'); $d->addDay()) {
        ($this->poser)($d->dayOfYear % 2 ? $a : $b, $poste, $d->toDateString(), '07:00', '07:00');
    }

    expect(($this->prolonger)()['skipped'])->toBe(0);

    expect(Vacation::query()->whereDate('date_debut', '2026-12-01')->sole()->date_fin->toDateString())->toBe('2026-12-02');
});

it('ne touche pas une affectation sans date de fin', function () {
    $poste = ($this->poste)();
    $a = ($this->agent)('A');
    Vacation::query()->create([
        'agent_id' => $a->id,
        'site_id' => $this->site->id,
        'poste_id' => $poste->id,
        'date_debut' => '2026-10-01',
        'date_fin' => null,
        'heure_debut' => '06:00',
        'heure_fin' => '18:00',
        'statut' => 'planifiee',
    ]);

    ($this->prolonger)(reprendre: true);

    expect(Vacation::query()->where('agent_id', $a->id)->count())->toBe(1);
});

it('reconduit une vacation sur période sans doublon ni trou', function () {
    $poste = ($this->poste)();
    $a = ($this->agent)('A');
    Vacation::query()->create([
        'agent_id' => $a->id,
        'site_id' => $this->site->id,
        'poste_id' => $poste->id,
        'date_debut' => '2026-10-26',
        'date_fin' => '2026-11-08',
        'heure_debut' => '06:00',
        'heure_fin' => '18:00',
        'statut' => 'planifiee',
    ]);

    ($this->prolonger)();

    $jours = [];
    foreach (Vacation::query()->where('agent_id', $a->id)->get() as $v) {
        array_push($jours, ...ShiftSchedule::fromVacation($v)->days());
    }

    expect(count($jours))->toBe(count(array_unique($jours)))
        ->and($jours)->toContain('2026-11-05', '2026-11-09', '2026-12-14');
});

it('crée le trou d’une nuit reconduite avec sa bonne date de fin', function () {
    $poste = ($this->poste)();
    $a = ($this->agent)('A');
    ($this->semaine)($a, $poste, '2026-10-26', '18:00', '06:00');
    ($this->semaine)($a, $poste, '2026-11-02', '18:00', '06:00');
    ($this->absence)($a, '2026-11-20', '2026-11-20')->assertCreated();

    ($this->prolonger)();

    $trou = Vacation::query()->where('agent_id', $a->id)->where('statut', 'a_recouvrir')->sole();

    expect($trou->date_debut->toDateString())->toBe('2026-11-20')
        ->and($trou->date_fin->toDateString())->toBe('2026-11-21');
});

it('saute sans erreur un agent déjà pris ailleurs et garde les autres jours', function () {
    $poste = ($this->poste)();
    $ailleurs = ($this->poste)();
    $a = ($this->agent)('A');
    ($this->semaine)($a, $poste, '2026-10-26');
    ($this->semaine)($a, $poste, '2026-11-02');
    ($this->poser)($a, $ailleurs, '2026-11-20', '10:00', '12:00');

    $r = ($this->prolonger)();

    expect($r['skipped'])->toBeGreaterThanOrEqual(1)
        ->and(($this->jours)($a))->toContain('2026-11-19', '2026-11-21');
});

/* ---------- Pointage ---------- */

it('rattache un contrôle de nuit après minuit à la bonne nuit', function () {
    $poste = ($this->poste)();
    $a = ($this->agent)('A');
    ($this->poser)($a, $poste, '2026-10-30', '18:00', '06:00');
    Controle::query()->create([
        'controle_agent_id' => $a->id,
        'site_id' => $this->site->id,
        'effectue_at' => '2026-10-31 02:00:00',
        'resultat' => 'present',
    ]);

    $this->actingAs($this->admin)
        ->getJson('/api/v1/vacations/pointage?date_debut=2026-10-30&date_fin=2026-10-30&agent_id='.$a->id)
        ->assertOk()
        ->assertJsonPath('data.0.controle', 'present')
        ->assertJsonPath('meta.present', 1);
});

it('compte un jour supplémentaire par date, même avec deux créneaux', function () {
    $poste = ($this->poste)();
    $a = ($this->agent)('A');
    ($this->poser)($a, $poste, '2026-10-30', '06:00', '10:00', ['heures_supplementaires' => true]);
    ($this->poser)($a, $poste, '2026-10-30', '14:00', '18:00', ['heures_supplementaires' => true]);
    foreach (['08:00', '16:00'] as $h) {
        Controle::query()->create([
            'controle_agent_id' => $a->id,
            'site_id' => $this->site->id,
            'effectue_at' => "2026-10-30 {$h}:00",
            'resultat' => 'present',
        ]);
    }

    $m = $this->actingAs($this->admin)
        ->getJson('/api/v1/vacations/pointage?date_debut=2026-10-30&date_fin=2026-10-30&agent_id='.$a->id)
        ->json('meta');

    expect($m['jours_supplementaires'])->toBe(1)
        ->and((float) $m['heures_supplementaires'])->toBe(8.0);
});

/* ---------- Invariants sur des opérations aléatoires ---------- */

it('garde ses invariants après des centaines d’opérations aléatoires', function (int $graine) {
    mt_srand($graine);

    $agents = collect(range(1, 8))->map(fn ($i) => ($this->agent)("R{$i}"));
    $postes = collect([
        ($this->poste)(['agents_requis' => 1]),
        ($this->poste)(['agents_requis' => 2]),
        ($this->poste)(['agents_requis' => 2, 'heure_debut_nuit' => '18:00', 'heure_fin_nuit' => '06:00']),
        ($this->poste)(['agents_requis' => 3, 'heure_debut_nuit' => '18:00', 'heure_fin_nuit' => '06:00']),
    ]);
    $creneaux = [['06:00', '18:00'], ['18:00', '06:00'], ['08:00', '16:00'], ['22:00', '06:00'], ['14:00', '22:00']];

    for ($i = 0; $i < 80; $i++) {
        $date = Carbon::parse('2026-11-09')->addDays(mt_rand(0, 13))->toDateString();
        $action = mt_rand(1, 10);

        if ($action <= 7) {
            [$debut, $fin] = $creneaux[mt_rand(0, 4)];
            $r = ($this->creer)($agents[mt_rand(0, 7)], $postes[mt_rand(0, 3)], $date, $debut, $fin);
            expect($r->status())->toBeIn([201, 409, 422]);
        } elseif ($action === 8) {
            ($this->absence)($agents[mt_rand(0, 7)], $date, $date);
            Agent::query()->whereIn('statut', ['conge', 'malade'])->update(['statut' => 'disponible']);
        } elseif ($action === 9) {
            $trou = Vacation::query()->where('statut', 'a_recouvrir')->inRandomOrder()->first();
            if ($trou) {
                $r = $this->actingAs($this->admin)->postJson("/api/v1/vacations/{$trou->id}/recouvrir", ['agent_id' => $agents[mt_rand(0, 7)]->id]);
                expect($r->status())->toBeIn([201, 409, 422]);
            }
        } else {
            $v = Vacation::query()->where('statut', 'planifiee')->inRandomOrder()->first();
            if ($v) {
                expect($this->actingAs($this->admin)->deleteJson("/api/v1/vacations/{$v->id}")->status())->toBeLessThan(500);
            }
        }
    }

    // 1. Jamais deux créneaux actifs qui se chevauchent pour un même agent.
    $actives = Vacation::query()->whereNotIn('statut', ['annulee', 'a_recouvrir'])->get();
    foreach ($actives->groupBy('agent_id') as $liste) {
        $liste = $liste->values();
        foreach ($liste as $k => $v) {
            foreach ($liste->slice($k + 1) as $w) {
                expect(ShiftSchedule::fromVacation($v)->overlaps(ShiftSchedule::fromVacation($w)))
                    ->toBeFalse("Chevauchement {$v->id} / {$w->id}");
            }
        }
    }

    // 2. Jamais de sur-effectif.
    foreach ($postes as $poste) {
        for ($d = Carbon::parse('2026-11-09'); $d->lte('2026-11-22'); $d->addDay()) {
            expect(($this->couverture)($poste, $d->toDateString())['statut'])
                ->not->toBe('sur_effectif', "{$poste->nom} {$d->toDateString()}");
        }
    }

    // 3. Pas de vacation active sur une absence approuvée.
    foreach (Absence::query()->where('statut', 'approuvee')->get() as $absence) {
        $bloquee = $actives->where('agent_id', $absence->agent_id)
            ->first(fn ($v) => $v->date_debut->toDateString() === $absence->date_debut->toDateString());
        expect($bloquee)->toBeNull("Vacation active sur absence {$absence->date_debut->toDateString()}");
    }

    // 4. La reconduction respecte aussi ces règles et ne crée jamais de doublon.
    ($this->prolonger)(reprendre: true);
    ($this->prolonger)(reprendre: true);

    $cles = Vacation::query()->where('statut', '!=', 'annulee')->get()
        ->map(fn ($v) => "{$v->agent_id}|{$v->poste_id}|{$v->date_debut->toDateString()}|{$v->heure_debut}");
    expect($cles->count())->toBe($cles->unique()->count());

    $actives = Vacation::query()->whereNotIn('statut', ['annulee', 'a_recouvrir'])->where('reconduite', true)->get();
    foreach ($actives as $v) {
        $autres = Vacation::query()->where('agent_id', $v->agent_id)->whereKeyNot($v->id)
            ->whereNotIn('statut', ['annulee', 'a_recouvrir'])
            ->whereDate('date_debut', '>=', $v->date_debut->copy()->subDay())
            ->whereDate('date_debut', '<=', $v->date_debut->copy()->addDay())
            ->get();
        foreach ($autres as $w) {
            expect(ShiftSchedule::fromVacation($v)->overlaps(ShiftSchedule::fromVacation($w)))->toBeFalse();
        }
    }
})->with([7, 42]);
