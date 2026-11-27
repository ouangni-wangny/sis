<?php

use App\Application\Operation\PublierPlanningSemaineAction;
use App\Application\Operation\UpsertPlanningSemaineAction;
use App\Domain\Shared\Enums\TypeAgent;
use App\Models\Agent;
use App\Models\Client;
use App\Models\Contrat;
use App\Models\Grade;
use App\Models\PlanningSemaine;
use App\Models\Poste;
use App\Models\Site;
use App\Models\User;
use App\Models\Vacation;
use App\Models\Zone;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    Carbon::setTestNow('2026-11-09 08:00:00'); // Lundi S46/2026
    $this->seed(RolePermissionSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('super-admin');

    $this->gradeAgent = Grade::query()->create(['libelle' => 'Agent', 'type_agent' => TypeAgent::Agent]);
    $this->gradeRondier = Grade::query()->where('est_rondier', true)->firstOrFail();

    $creer = function (string $nom, Grade $grade): Agent {
        $agent = Agent::query()->create([
            'grade_id' => $grade->id,
            'type' => TypeAgent::Agent,
            'nom' => $nom,
            'prenom' => 'Test',
            'matricule' => 'RD-'.$nom,
            'statut' => 'disponible',
        ]);
        Contrat::query()->where('agent_id', $agent->id)->forceDelete();
        Contrat::query()->create([
            'agent_id' => $agent->id,
            'type' => 'cdi',
            'date_debut' => '2026-01-01',
            'statut' => 'actif',
        ]);

        return $agent;
    };
    $this->titulaire = $creer('Titulaire', $this->gradeAgent);
    $this->rondier = $creer('Releve', $this->gradeRondier);

    $client = Client::query()->create(['type' => 'entreprise', 'raison_sociale' => 'Client RD', 'statut' => 'actif']);
    $this->site = Site::query()->create([
        'client_id' => $client->id,
        'zone_id' => Zone::query()->create(['nom' => 'Zone RD'])->id,
        'nom' => 'Site RD',
    ]);
    $this->poste = Poste::query()->create([
        'site_id' => $this->site->id,
        'nom' => 'Portail',
        'agents_requis' => 1,
        'heure_debut' => '06:30',
        'heure_fin' => '18:30',
    ]);

    // Titulaire du lundi au samedi ; dimanche = repos.
    $planning = app(UpsertPlanningSemaineAction::class)->execute([
        'poste_id' => $this->poste->id,
        'annee_iso' => 2026,
        'semaine_iso' => 46,
        'creneaux' => collect(range(1, 6))->map(fn (int $j) => [
            'agent_id' => $this->titulaire->id,
            'jour' => $j,
            'heure_debut' => '06:30',
            'heure_fin' => '18:30',
            'quart' => 'jour',
        ])->all(),
    ]);
    app(PublierPlanningSemaineAction::class)->execute($planning);

    $this->trous = fn () => collect($this->actingAs($this->admin)
        ->getJson('/api/v1/planning-rondiers?lundi=2026-11-09')
        ->assertOk()
        ->json('data.trous'))->where('poste_id', $this->poste->id)->values();
});

afterEach(function () {
    Carbon::setTestNow();
});

it('le grade Rondier existe et seul un grade de type agent peut être rondier', function () {
    expect($this->gradeRondier->libelle)->toBe('Rondier')
        ->and($this->gradeRondier->type_agent)->toBe(TypeAgent::Agent);

    $this->actingAs($this->admin)
        ->postJson('/api/v1/grades', ['libelle' => 'Ctrl', 'type_agent' => 'controleur', 'est_rondier' => true])
        ->assertCreated()
        ->assertJsonPath('data.est_rondier', false);

    $this->actingAs($this->admin)
        ->getJson('/api/v1/agents?rondier=1&per_page=50')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $this->rondier->id);
});

it('liste le repos du titulaire comme un trou et les rondiers de la semaine', function () {
    $trous = ($this->trous)();

    expect($trous)->toHaveCount(1)
        ->and($trous[0]['date'])->toBe('2026-11-15')
        ->and($trous[0]['motif'])->toBe('repos')
        ->and($trous[0]['titulaire'])->toBe('Test Titulaire')
        ->and($trous[0]['heure_debut'])->toBe('06:30');

    $this->actingAs($this->admin)
        ->getJson('/api/v1/planning-rondiers?lundi=2026-11-09')
        ->assertJsonPath('data.rondiers.0.id', $this->rondier->id);
});

it('affecte un rondier sur le repos : vacation créée, créneau ajouté au modèle, trou comblé', function () {
    $this->actingAs($this->admin)
        ->postJson('/api/v1/planning-rondiers/affecter', [
            'agent_id' => $this->rondier->id,
            'poste_id' => $this->poste->id,
            'date' => '2026-11-15',
        ])
        ->assertCreated();

    $vacation = Vacation::query()->where('agent_id', $this->rondier->id)->firstOrFail();
    $modele = PlanningSemaine::query()->where('poste_id', $this->poste->id)->firstOrFail();

    expect($vacation->est_remplacement)->toBeTrue()
        ->and($vacation->date_debut->toDateString())->toBe('2026-11-15')
        ->and($modele->creneaux()->where('agent_id', $this->rondier->id)->pluck('jour')->all())->toBe([7])
        ->and($modele->statut->value)->toBe('publiee')
        ->and(($this->trous)())->toHaveCount(0);

    // Couverture du dimanche : complète.
    $this->actingAs($this->admin)
        ->getJson('/api/v1/postes/coverage?date=2026-11-15&site_id='.$this->site->id)
        ->assertJsonPath('data.0.statut', 'ok');

    // Retoucher le planning du poste ne retire pas le rondier du modèle.
    app(UpsertPlanningSemaineAction::class)->execute([
        'poste_id' => $this->poste->id,
        'annee_iso' => 2026,
        'semaine_iso' => 46,
        'creneaux' => [[
            'agent_id' => $this->titulaire->id,
            'jour' => 2,
            'heure_debut' => '06:30',
            'heure_fin' => '18:30',
            'quart' => 'jour',
        ]],
    ]);
    expect($modele->creneaux()->where('agent_id', $this->rondier->id)->count())->toBe(1);
});

it('refuse un agent qui n’est pas rondier', function () {
    $autre = Agent::query()->create([
        'grade_id' => $this->gradeAgent->id,
        'type' => TypeAgent::Agent,
        'nom' => 'Pas',
        'prenom' => 'Rondier',
        'matricule' => 'RD-X',
        'statut' => 'disponible',
    ]);

    $this->actingAs($this->admin)
        ->postJson('/api/v1/planning-rondiers/affecter', [
            'agent_id' => $autre->id,
            'poste_id' => $this->poste->id,
            'date' => '2026-11-15',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('agent_id');
});

it('retire un rondier : vacation et créneau supprimés, le trou revient', function () {
    $id = $this->actingAs($this->admin)
        ->postJson('/api/v1/planning-rondiers/affecter', [
            'agent_id' => $this->rondier->id,
            'poste_id' => $this->poste->id,
            'date' => '2026-11-15',
        ])
        ->assertCreated()
        ->json('data.id');

    $this->actingAs($this->admin)
        ->deleteJson("/api/v1/planning-rondiers/vacations/{$id}")
        ->assertNoContent();

    expect(Vacation::query()->whereKey($id)->exists())->toBeFalse()
        ->and(PlanningSemaine::query()->firstOrFail()->creneaux()->where('agent_id', $this->rondier->id)->count())->toBe(0)
        ->and(($this->trous)())->toHaveCount(1);
});

it('classe les rondiers à part sur la feuille de pointage', function () {
    $this->actingAs($this->admin)
        ->postJson('/api/v1/planning-rondiers/affecter', [
            'agent_id' => $this->rondier->id,
            'poste_id' => $this->poste->id,
            'date' => '2026-11-15',
        ])
        ->assertCreated();

    $categories = collect($this->actingAs($this->admin)
        ->getJson('/api/v1/vacations/pointage/feuille?date_debut=2026-11-09&date_fin=2026-11-15')
        ->assertOk()
        ->json('data.categories'));

    expect($categories->pluck('libelle')->all())->toBe(['Agents postés', 'Rondiers'])
        ->and($categories[1]['agents'][0]['agent_id'])->toBe($this->rondier->id);
});

it('planifie la semaine d’un rondier en une fois, puis la modifie', function () {
    $guerite = Poste::query()->create([
        'site_id' => $this->site->id,
        'nom' => 'Guérite',
        'agents_requis' => 1,
        'heure_debut' => '18:30',
        'heure_fin' => '06:30',
    ]);

    $this->actingAs($this->admin)
        ->postJson('/api/v1/planning-rondiers/planifier', [
            'agent_id' => $this->rondier->id,
            'jours' => [
                ['date' => '2026-11-12', 'poste_id' => $guerite->id],
                ['date' => '2026-11-15', 'poste_id' => $this->poste->id],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('data.creees', 2);

    $this->actingAs($this->admin)
        ->postJson('/api/v1/planning-rondiers/planifier', [
            'agent_id' => $this->rondier->id,
            'jours' => [
                ['date' => '2026-11-12', 'poste_id' => null],
                ['date' => '2026-11-15', 'poste_id' => $this->poste->id],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('data.retirees', 1)
        ->assertJsonPath('data.conservees', 1)
        ->assertJsonPath('data.creees', 0);

    expect(Vacation::query()->where('agent_id', $this->rondier->id)->pluck('date_debut')
        ->map(fn ($d) => $d->toDateString())->all())->toBe(['2026-11-15']);
});

it('n’enregistre rien si un jour est refusé et nomme ce jour', function () {
    // Lundi : le titulaire tient déjà le poste (effectif 1 atteint).
    $this->actingAs($this->admin)
        ->postJson('/api/v1/planning-rondiers/planifier', [
            'agent_id' => $this->rondier->id,
            'jours' => [
                ['date' => '2026-11-15', 'poste_id' => $this->poste->id],
                ['date' => '2026-11-09', 'poste_id' => $this->poste->id],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('jours');

    expect(Vacation::query()->where('agent_id', $this->rondier->id)->count())->toBe(0);
});

it('placer un rondier sur une absence la recouvre, le retirer la rend à recouvrir', function () {
    $mercredi = Vacation::query()->where('agent_id', $this->titulaire->id)->whereDate('date_debut', '2026-11-11')->firstOrFail();
    $mercredi->update(['statut' => 'a_recouvrir']);

    $id = $this->actingAs($this->admin)
        ->postJson('/api/v1/planning-rondiers/affecter', [
            'agent_id' => $this->rondier->id,
            'poste_id' => $this->poste->id,
            'date' => '2026-11-11',
        ])
        ->assertCreated()
        ->json('data.id');

    expect($mercredi->fresh()->statut->value)->toBe('annulee')
        ->and($mercredi->fresh()->remplacee)->toBeTrue();

    $this->actingAs($this->admin)->deleteJson("/api/v1/planning-rondiers/vacations/{$id}")->assertNoContent();

    expect($mercredi->fresh()->statut->value)->toBe('a_recouvrir')
        ->and($mercredi->fresh()->remplacee)->toBeFalse();
});

it('réserve le planning des rondiers au siège (refusé aux comptes terrain)', function () {
    foreach (['agent' => $this->titulaire, 'controleur' => null] as $role => $agent) {
        if (! $agent) {
            $agent = Agent::query()->create([
                'grade_id' => Grade::query()->create(['libelle' => 'Ctrl', 'type_agent' => TypeAgent::Controleur])->id,
                'type' => TypeAgent::Controleur,
                'nom' => 'Ctrl',
                'prenom' => 'Test',
                'matricule' => 'RD-CTRL',
                'statut' => 'en_activite',
            ]);
        }
        $user = User::factory()->create();
        $user->assignRole($role);
        $agent->update(['user_id' => $user->id]);

        $this->actingAs($user->fresh())
            ->getJson('/api/v1/planning-rondiers?lundi=2026-11-09')
            ->assertForbidden();
    }
});

it('refuse de planifier des jours de deux semaines différentes', function () {
    $this->actingAs($this->admin)->postJson('/api/v1/planning-rondiers/planifier', [
        'agent_id' => $this->rondier->id,
        'jours' => [
            ['date' => '2026-11-15', 'poste_id' => $this->poste->id, 'quart' => 'jour'],
            ['date' => '2026-11-16', 'poste_id' => $this->poste->id, 'quart' => 'jour'],
        ],
    ])->assertUnprocessable()->assertJsonValidationErrors(['jours']);
});

it('limite la vacation d’un rondier à son jour et refuse un poste déjà tenu', function () {
    $affecter = fn (string $date) => $this->actingAs($this->admin)
        ->postJson('/api/v1/planning-rondiers/affecter', [
            'agent_id' => $this->rondier->id,
            'poste_id' => $this->poste->id,
            'date' => $date,
        ]);

    $affecter('2026-11-15')->assertCreated();
    $vacation = Vacation::query()->where('agent_id', $this->rondier->id)->firstOrFail();
    expect($vacation->date_fin?->toDateString())->toBe('2026-11-15');

    // Mardi : le titulaire tient le poste (effectif 1) → refusé, même si le modèle grossit.
    $affecter('2026-11-10')->assertUnprocessable()->assertJsonValidationErrors(['poste_id']);
    expect(Vacation::query()->where('agent_id', $this->rondier->id)->count())->toBe(1);
});

it('ne rouvre pas l’absence d’un autre quand on retire un rondier placé sur un repos', function () {
    $poste2 = Poste::query()->create([
        'site_id' => $this->site->id, 'nom' => 'Parking', 'agents_requis' => 2,
        'heure_debut' => '06:30', 'heure_fin' => '18:30',
    ]);
    $vac = fn (Agent $a, array $extra) => Vacation::query()->create([
        'agent_id' => $a->id, 'site_id' => $this->site->id, 'poste_id' => $poste2->id,
        'date_debut' => '2026-11-15', 'date_fin' => '2026-11-15',
        'heure_debut' => '06:30', 'heure_fin' => '18:30', ...$extra,
    ]);
    // Le titulaire est absent et déjà remplacé par un collègue.
    $trou = $vac($this->titulaire, ['statut' => 'annulee', 'remplacee' => true]);
    $collegue = Agent::query()->create([
        'grade_id' => $this->gradeAgent->id, 'type' => TypeAgent::Agent,
        'nom' => 'Collegue', 'prenom' => 'Test', 'matricule' => 'RD-COL', 'statut' => 'disponible',
    ]);
    $vac($collegue, ['statut' => 'planifiee', 'est_remplacement' => true]);

    // Rondier sur la seconde place (libre), puis retiré.
    $id = $this->actingAs($this->admin)->postJson('/api/v1/planning-rondiers/affecter', [
        'agent_id' => $this->rondier->id, 'poste_id' => $poste2->id, 'date' => '2026-11-15',
    ])->assertCreated()->json('data.id');
    $this->actingAs($this->admin)->deleteJson("/api/v1/planning-rondiers/vacations/{$id}")->assertNoContent();

    expect($trou->fresh()->statut->value)->toBe('annulee')
        ->and($trou->fresh()->remplacee)->toBeTrue();
});

it('une republication ne rouvre pas une absence déjà recouverte', function () {
    $mardi = Vacation::query()->where('agent_id', $this->titulaire->id)->whereDate('date_debut', '2026-11-10')->firstOrFail();
    $mardi->update(['statut' => 'a_recouvrir']);
    app(\App\Application\Operation\RecouvrirVacationAction::class)->execute($mardi, (string) $this->rondier->id);

    $modele = PlanningSemaine::query()->where('poste_id', $this->poste->id)->firstOrFail();
    app(PublierPlanningSemaineAction::class)->execute($modele->fresh());

    expect(Vacation::query()->where('agent_id', $this->titulaire->id)->whereDate('date_debut', '2026-11-10')
        ->where('statut', 'a_recouvrir')->exists())->toBeFalse()
        ->and(Vacation::query()->where('agent_id', $this->rondier->id)->whereDate('date_debut', '2026-11-10')
            ->where('statut', 'planifiee')->exists())->toBeTrue();
});

it('ne compte pas de trou pour un poste tenu par une vacation sur période', function () {
    $poste3 = Poste::query()->create([
        'site_id' => $this->site->id, 'nom' => 'Accueil', 'agents_requis' => 1,
        'heure_debut' => '06:30', 'heure_fin' => '18:30',
    ]);
    Vacation::query()->create([
        'agent_id' => $this->titulaire->id, 'site_id' => $this->site->id, 'poste_id' => $poste3->id,
        'date_debut' => '2026-11-02', 'date_fin' => '2026-11-20',
        'heure_debut' => '06:30', 'heure_fin' => '18:30', 'statut' => 'planifiee',
    ]);

    $trous = collect($this->actingAs($this->admin)
        ->getJson('/api/v1/planning-rondiers?lundi=2026-11-09')
        ->assertOk()
        ->json('data.trous'))->where('poste_id', $poste3->id);

    expect($trous)->toHaveCount(0);
});

it('annuler les jours à venir retire les rondiers et permet de republier', function () {
    $this->actingAs($this->admin)->postJson('/api/v1/planning-rondiers/affecter', [
        'agent_id' => $this->rondier->id, 'poste_id' => $this->poste->id, 'date' => '2026-11-15',
    ])->assertCreated();

    $this->actingAs($this->admin)->postJson('/api/v1/planning-semaines/annuler', [
        'poste_id' => $this->poste->id, 'annee_iso' => 2026, 'semaine_iso' => 46,
    ])->assertOk();
    expect(Vacation::query()->where('agent_id', $this->rondier->id)->exists())->toBeFalse();

    // Le titulaire sur 7 jours : la publication passe.
    $planning = app(UpsertPlanningSemaineAction::class)->execute([
        'poste_id' => $this->poste->id, 'annee_iso' => 2026, 'semaine_iso' => 46,
        'creneaux' => collect(range(1, 7))->map(fn (int $j) => [
            'agent_id' => $this->titulaire->id, 'jour' => $j,
            'heure_debut' => '06:30', 'heure_fin' => '18:30', 'quart' => 'jour',
        ])->all(),
    ]);
    app(PublierPlanningSemaineAction::class)->execute($planning);
    expect(Vacation::query()->where('agent_id', $this->titulaire->id)->whereDate('date_debut', '2026-11-15')->exists())->toBeTrue();
});
