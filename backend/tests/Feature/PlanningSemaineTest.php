<?php

use App\Application\Operation\DupliquerPlanningSemaineAction;
use App\Application\Operation\PublierPlanningSemaineAction;
use App\Application\Operation\UpsertPlanningSemaineAction;
use App\Domain\Shared\Enums\StatutPlanningSemaine;
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
    Carbon::setTestNow('2026-11-09 10:00:00'); // Lundi S46/2026
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
            'matricule' => 'PS-'.$nom,
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

    $this->jour = ($this->creerAgent)('Jour');
    $this->client = Client::query()->create([
        'type' => 'entreprise',
        'raison_sociale' => 'Client',
        'statut' => 'actif',
    ]);
    $this->site = Site::query()->create([
        'client_id' => $this->client->id,
        'zone_id' => Zone::query()->create(['nom' => 'Z'])->id,
        'nom' => 'Cocody',
    ]);
    $this->poste = Poste::query()->create([
        'site_id' => $this->site->id,
        'nom' => 'Entrée',
        'agents_requis' => 1,
        'heure_debut' => '06:30',
        'heure_fin' => '18:30',
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('publie une semaine et génère les vacations du lundi au dimanche', function () {
    $planning = app(UpsertPlanningSemaineAction::class)->execute([
        'poste_id' => $this->poste->id,
        'annee_iso' => 2026,
        'semaine_iso' => 46,
        'creneaux' => [
            [
                'agent_id' => $this->jour->id,
                'jour' => 1,
                'heure_debut' => '06:30',
                'heure_fin' => '18:30',
                'quart' => 'jour',
            ],
            [
                'agent_id' => $this->jour->id,
                'jour' => 3,
                'heure_debut' => '06:30',
                'heure_fin' => '18:30',
                'quart' => 'jour',
            ],
        ],
    ]);

    expect($planning->statut)->toBe(StatutPlanningSemaine::Brouillon);

    $publie = app(PublierPlanningSemaineAction::class)->execute($planning);

    expect($publie->statut)->toBe(StatutPlanningSemaine::Publiee);

    $dates = Vacation::query()
        ->where('poste_id', $this->poste->id)
        ->where('statut', 'planifiee')
        ->orderBy('date_debut')
        ->pluck('date_debut')
        ->map(fn ($d) => $d->toDateString())
        ->all();

    expect($dates)->toBe(['2026-11-09', '2026-11-11']);
});

it('modifie S+1 sans toucher aux vacations de S', function () {
    $s46 = app(UpsertPlanningSemaineAction::class)->execute([
        'poste_id' => $this->poste->id,
        'annee_iso' => 2026,
        'semaine_iso' => 46,
        'creneaux' => [[
            'agent_id' => $this->jour->id,
            'jour' => 1,
            'heure_debut' => '06:30',
            'heure_fin' => '18:30',
        ]],
    ]);
    app(PublierPlanningSemaineAction::class)->execute($s46);

    $s47 = app(UpsertPlanningSemaineAction::class)->execute([
        'poste_id' => $this->poste->id,
        'annee_iso' => 2026,
        'semaine_iso' => 47,
        'creneaux' => [[
            'agent_id' => $this->jour->id,
            'jour' => 2,
            'heure_debut' => '06:30',
            'heure_fin' => '18:30',
        ]],
    ]);
    app(PublierPlanningSemaineAction::class)->execute($s47);

    $datesS46 = Vacation::query()
        ->where('poste_id', $this->poste->id)
        ->whereDate('date_debut', '>=', '2026-11-09')
        ->whereDate('date_debut', '<=', '2026-11-15')
        ->pluck('date_debut')
        ->map(fn ($d) => $d->toDateString())
        ->all();

    $datesS47 = Vacation::query()
        ->where('poste_id', $this->poste->id)
        ->whereDate('date_debut', '>=', '2026-11-16')
        ->whereDate('date_debut', '<=', '2026-11-22')
        ->pluck('date_debut')
        ->map(fn ($d) => $d->toDateString())
        ->all();

    expect($datesS46)->toBe(['2026-11-09'])
        ->and($datesS47)->toBe(['2026-11-17']);
});

it('duplique la semaine précédente vers un brouillon S+1', function () {
    app(UpsertPlanningSemaineAction::class)->execute([
        'poste_id' => $this->poste->id,
        'annee_iso' => 2026,
        'semaine_iso' => 46,
        'creneaux' => [
            [
                'agent_id' => $this->jour->id,
                'jour' => 1,
                'heure_debut' => '06:30',
                'heure_fin' => '18:30',
            ],
            [
                'agent_id' => $this->jour->id,
                'jour' => 5,
                'heure_debut' => '06:30',
                'heure_fin' => '18:30',
            ],
        ],
    ]);

    $copie = app(DupliquerPlanningSemaineAction::class)->execute([
        'poste_id' => $this->poste->id,
        'annee_iso' => 2026,
        'semaine_iso' => 47,
    ]);

    expect($copie->semaine_iso)->toBe(47)
        ->and($copie->statut)->toBe(StatutPlanningSemaine::Brouillon)
        ->and($copie->creneaux)->toHaveCount(2)
        ->and($copie->creneaux->pluck('jour')->sort()->values()->all())->toBe([1, 5]);
});

it('remplace les vacations planifiee de la semaine à la republication', function () {
    $planning = app(UpsertPlanningSemaineAction::class)->execute([
        'poste_id' => $this->poste->id,
        'annee_iso' => 2026,
        'semaine_iso' => 46,
        'creneaux' => [[
            'agent_id' => $this->jour->id,
            'jour' => 1,
            'heure_debut' => '06:30',
            'heure_fin' => '18:30',
        ]],
    ]);
    app(PublierPlanningSemaineAction::class)->execute($planning);

    expect(Vacation::query()->where('poste_id', $this->poste->id)->count())->toBe(1);

    $planning = app(UpsertPlanningSemaineAction::class)->execute([
        'poste_id' => $this->poste->id,
        'annee_iso' => 2026,
        'semaine_iso' => 46,
        'creneaux' => [
            [
                'agent_id' => $this->jour->id,
                'jour' => 2,
                'heure_debut' => '06:30',
                'heure_fin' => '18:30',
            ],
            [
                'agent_id' => $this->jour->id,
                'jour' => 4,
                'heure_debut' => '06:30',
                'heure_fin' => '18:30',
            ],
        ],
    ]);

    expect($planning->fresh()->statut)->toBe(StatutPlanningSemaine::Brouillon);

    app(PublierPlanningSemaineAction::class)->execute($planning->fresh());

    $dates = Vacation::query()
        ->where('poste_id', $this->poste->id)
        ->where('statut', 'planifiee')
        ->orderBy('date_debut')
        ->pluck('date_debut')
        ->map(fn ($d) => $d->toDateString())
        ->all();

    expect($dates)->toBe(['2026-11-10', '2026-11-12']);
});

it('conserve les créneaux des jours déjà passés à l’enregistrement mid-semaine', function () {
    Carbon::setTestNow('2026-11-11 10:00:00'); // mercredi

    $planning = app(UpsertPlanningSemaineAction::class)->execute([
        'poste_id' => $this->poste->id,
        'annee_iso' => 2026,
        'semaine_iso' => 46,
        'creneaux' => [
            [
                'agent_id' => $this->jour->id,
                'jour' => 1,
                'heure_debut' => '06:30',
                'heure_fin' => '18:30',
            ],
            [
                'agent_id' => $this->jour->id,
                'jour' => 2,
                'heure_debut' => '06:30',
                'heure_fin' => '18:30',
            ],
            [
                'agent_id' => $this->jour->id,
                'jour' => 3,
                'heure_debut' => '06:30',
                'heure_fin' => '18:30',
            ],
        ],
    ]);

    // Client n’envoie plus lun/mar (verrouillés côté UI) : ils doivent rester.
    $maj = app(UpsertPlanningSemaineAction::class)->execute([
        'poste_id' => $this->poste->id,
        'annee_iso' => 2026,
        'semaine_iso' => 46,
        'creneaux' => [[
            'agent_id' => $this->jour->id,
            'jour' => 3,
            'heure_debut' => '06:30',
            'heure_fin' => '18:30',
        ]],
    ]);

    expect($maj->creneaux->pluck('jour')->sort()->values()->all())->toBe([1, 2, 3]);

    $copie = app(DupliquerPlanningSemaineAction::class)->execute([
        'poste_id' => $this->poste->id,
        'annee_iso' => 2026,
        'semaine_iso' => 47,
    ]);

    expect($copie->creneaux->pluck('jour')->sort()->values()->all())->toBe([1, 2, 3]);
});

it('marque est_remplacement sur création avec remplacement et ne touche pas le template', function () {
    $remplacant = ($this->creerAgent)('Rempl');

    app(UpsertPlanningSemaineAction::class)->execute([
        'poste_id' => $this->poste->id,
        'annee_iso' => 2026,
        'semaine_iso' => 46,
        'creneaux' => [[
            'agent_id' => $this->jour->id,
            'jour' => 4,
            'heure_debut' => '06:30',
            'heure_fin' => '18:30',
        ]],
    ]);

    $avant = PlanningSemaine::query()
        ->where('poste_id', $this->poste->id)
        ->where('semaine_iso', 46)
        ->first()
        ->creneaux()
        ->count();

    $vacation = app(\App\Application\Operation\CreateVacationAction::class)->execute([
        'agent_id' => $remplacant->id,
        'site_id' => $this->site->id,
        'poste_id' => $this->poste->id,
        'date_debut' => '2026-11-12',
        'heure_debut' => '06:30',
        'heure_fin' => '18:30',
        'remplacement' => true,
    ]);

    expect($vacation->est_remplacement)->toBeTrue()
        ->and(
            PlanningSemaine::query()
                ->where('poste_id', $this->poste->id)
                ->where('semaine_iso', 46)
                ->first()
                ->creneaux()
                ->count(),
        )->toBe($avant);
});

it('conserve les vacations est_remplacement à la publication', function () {
    $remplacant = ($this->creerAgent)('Rempl2');

    $planning = app(UpsertPlanningSemaineAction::class)->execute([
        'poste_id' => $this->poste->id,
        'annee_iso' => 2026,
        'semaine_iso' => 46,
        'creneaux' => [[
            'agent_id' => $this->jour->id,
            'jour' => 5,
            'heure_debut' => '06:30',
            'heure_fin' => '18:30',
        ]],
    ]);

    Vacation::query()->create([
        'agent_id' => $remplacant->id,
        'site_id' => $this->site->id,
        'poste_id' => $this->poste->id,
        'date_debut' => '2026-11-12',
        'date_fin' => '2026-11-12',
        'heure_debut' => '06:30',
        'heure_fin' => '18:30',
        'statut' => 'planifiee',
        'est_remplacement' => true,
    ]);

    app(PublierPlanningSemaineAction::class)->execute($planning);

    expect(
        Vacation::query()
            ->where('agent_id', $remplacant->id)
            ->where('est_remplacement', true)
            ->exists(),
    )->toBeTrue()
        ->and(
            Vacation::query()
                ->where('agent_id', $this->jour->id)
                ->whereDate('date_debut', '2026-11-13')
                ->where('est_remplacement', false)
                ->exists(),
        )->toBeTrue();
});

it('duplique uniquement les créneaux du template, pas une vacation remplacant', function () {
    $remplacant = ($this->creerAgent)('Rempl3');

    app(UpsertPlanningSemaineAction::class)->execute([
        'poste_id' => $this->poste->id,
        'annee_iso' => 2026,
        'semaine_iso' => 46,
        'creneaux' => [[
            'agent_id' => $this->jour->id,
            'jour' => 1,
            'heure_debut' => '06:30',
            'heure_fin' => '18:30',
        ]],
    ]);

    Vacation::query()->create([
        'agent_id' => $remplacant->id,
        'site_id' => $this->site->id,
        'poste_id' => $this->poste->id,
        'date_debut' => '2026-11-12',
        'date_fin' => '2026-11-12',
        'heure_debut' => '06:30',
        'heure_fin' => '18:30',
        'statut' => 'planifiee',
        'est_remplacement' => true,
    ]);

    $copie = app(DupliquerPlanningSemaineAction::class)->execute([
        'poste_id' => $this->poste->id,
        'annee_iso' => 2026,
        'semaine_iso' => 47,
    ]);

    expect($copie->creneaux)->toHaveCount(1)
        ->and($copie->creneaux->first()->agent_id)->toBe($this->jour->id)
        ->and($copie->creneaux->pluck('agent_id')->all())->not->toContain($remplacant->id);
});

it('expose l’API planning-semaines', function () {
    $this->actingAs($this->admin)
        ->putJson('/api/v1/planning-semaines', [
            'poste_id' => $this->poste->id,
            'annee_iso' => 2026,
            'semaine_iso' => 46,
            'creneaux' => [[
                'agent_id' => $this->jour->id,
                'jour' => 1,
                'heure_debut' => '06:30',
                'heure_fin' => '18:30',
                'quart' => 'jour',
            ]],
        ])
        ->assertOk()
        ->assertJsonPath('data.statut', 'brouillon')
        ->assertJsonPath('data.semaine_iso', 46);

    $this->actingAs($this->admin)
        ->postJson('/api/v1/planning-semaines/publier', [
            'poste_id' => $this->poste->id,
            'annee_iso' => 2026,
            'semaine_iso' => 46,
        ])
        ->assertOk()
        ->assertJsonPath('data.statut', 'publiee');

    expect(Vacation::query()->where('poste_id', $this->poste->id)->count())->toBe(1);

    $this->actingAs($this->admin)
        ->postJson('/api/v1/planning-semaines/dupliquer', [
            'poste_id' => $this->poste->id,
            'annee_iso' => 2026,
            'semaine_iso' => 47,
        ])
        ->assertOk()
        ->assertJsonPath('data.semaine_iso', 47)
        ->assertJsonPath('data.statut', 'brouillon');
});
