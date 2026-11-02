<?php

use App\Domain\Shared\Enums\Genre;
use App\Domain\Shared\Enums\TypeAgent;
use App\Models\Agent;
use App\Models\Contrat;
use App\Models\Grade;
use App\Models\User;
use App\Models\Ville;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('super-admin');

    $this->grade = Grade::query()->create([
        'libelle' => 'Agent',
        'type_agent' => TypeAgent::Agent,
    ]);
    $this->ville = Ville::query()->create(['libelle' => 'Dimbokro']);

    $this->creerAgent = function (?string $civilite, string $matricule, array $attributs = [], bool $contrat = true): Agent {
        $agent = Agent::query()->create([
            'grade_id' => $this->grade->id,
            'type' => TypeAgent::Agent,
            'nom' => 'Nom',
            'prenom' => $matricule,
            'civilite' => $civilite,
            'matricule' => $matricule,
            'statut' => 'disponible',
            ...$attributs,
        ]);

        Contrat::query()->where('agent_id', $agent->id)->forceDelete();
        if ($contrat) {
            Contrat::query()->create([
                'agent_id' => $agent->id,
                'type' => 'cdi',
                'date_debut' => now()->subYear()->toDateString(),
                'statut' => 'actif',
            ]);
        }

        return $agent;
    };

    ($this->creerAgent)('monsieur', 'H-1');
    ($this->creerAgent)('monsieur', 'H-2', ['ville_id' => $this->ville->id]);
    ($this->creerAgent)('madame', 'F-1', ['ville_id' => $this->ville->id]);
    ($this->creerAgent)('mademoiselle', 'F-2');
    ($this->creerAgent)(null, 'N-1');

    $this->matricules = fn (string $query) => collect(
        $this->actingAs($this->admin)->getJson("/api/v1/agents?{$query}")->assertOk()->json('data')
    )->pluck('matricule')->sort()->values()->all();
});

it('déduit le genre de la civilité quand il n’est pas saisi', function () {
    expect(Agent::query()->where('matricule', 'H-1')->value('genre'))->toBe(Genre::Homme)
        ->and(Agent::query()->where('matricule', 'F-1')->value('genre'))->toBe(Genre::Femme)
        ->and(Agent::query()->where('matricule', 'F-2')->value('genre'))->toBe(Genre::Femme)
        ->and(Agent::query()->where('matricule', 'N-1')->value('genre'))->toBeNull();
});

it('garde le genre saisi même s’il diffère de la civilité', function () {
    $agent = ($this->creerAgent)('monsieur', 'X-1', ['genre' => 'femme']);

    expect($agent->fresh()->genre)->toBe(Genre::Femme);
});

it('enregistre le genre à la création et à la modification d’une fiche', function () {
    $id = $this->actingAs($this->admin)->postJson('/api/v1/agents', [
        'grade_id' => $this->grade->id,
        'nom' => 'Nouvelle',
        'prenom' => 'Recrue',
        'genre' => 'femme',
    ])->assertCreated()
        ->assertJsonPath('data.genre', 'femme')
        ->json('data.id');

    $this->actingAs($this->admin)
        ->putJson("/api/v1/agents/{$id}", ['genre' => 'homme'])
        ->assertOk()
        ->assertJsonPath('data.genre', 'homme');
});

it('refuse un genre inconnu', function () {
    $this->actingAs($this->admin)->postJson('/api/v1/agents', [
        'grade_id' => $this->grade->id,
        'nom' => 'Nouvelle',
        'prenom' => 'Recrue',
        'genre' => 'autre',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['genre']);
});

it('remplit le genre des fiches existantes à la migration', function () {
    $migration = require database_path('migrations/2026_11_02_090000_add_genre_to_agents_table.php');
    $migration->down();
    $migration->up();

    $genres = DB::table('agents')->pluck('genre', 'matricule');

    expect($genres['H-1'])->toBe('homme')
        ->and($genres['F-1'])->toBe('femme')
        ->and($genres['F-2'])->toBe('femme')
        ->and($genres['N-1'])->toBeNull();
});

it('filtre les hommes', function () {
    expect(($this->matricules)('genre=homme'))->toBe(['H-1', 'H-2']);
});

it('filtre les femmes, madame et mademoiselle comprises', function () {
    expect(($this->matricules)('genre=femme'))->toBe(['F-1', 'F-2']);
});

it('isole les fiches sans genre', function () {
    expect(($this->matricules)('genre=non_renseigne'))->toBe(['N-1']);
});

it('liste tout le personnel sans filtre ou avec un genre inconnu', function () {
    expect(($this->matricules)(''))->toHaveCount(5)
        ->and(($this->matricules)('genre=autre'))->toHaveCount(5);
});

it('se combine avec les autres filtres', function () {
    expect(($this->matricules)("genre=femme&ville_id={$this->ville->id}"))->toBe(['F-1']);
});

it('donne le total paginé du genre choisi', function () {
    $this->actingAs($this->admin)
        ->getJson('/api/v1/agents?genre=femme&per_page=1')
        ->assertOk()
        ->assertJsonPath('meta.total', 2);
});

it('exporte le PDF avec le filtre', function () {
    $this->actingAs($this->admin)
        ->get('/api/v1/agents/export-pdf?genre=femme')
        ->assertOk();
});

it('calcule l’effectif par genre', function () {
    $this->actingAs($this->admin)
        ->getJson('/api/v1/agents/effectifs-genre')
        ->assertOk()
        ->assertExactJson(['data' => ['hommes' => 2, 'femmes' => 2, 'non_renseigne' => 1]]);
});

it('applique les filtres de la liste à l’effectif', function () {
    ($this->creerAgent)('monsieur', 'H-3', [], false);

    $this->actingAs($this->admin)
        ->getJson('/api/v1/agents/effectifs-genre?contrat_valide=1&ville_id='.$this->ville->id)
        ->assertOk()
        ->assertExactJson(['data' => ['hommes' => 1, 'femmes' => 1, 'non_renseigne' => 0]]);
});

it('exclut le personnel sans contrat valide et les fiches supprimées de l’effectif', function () {
    ($this->creerAgent)('monsieur', 'H-3', [], false);
    ($this->creerAgent)('madame', 'F-3')->delete();

    $this->actingAs($this->admin)
        ->getJson('/api/v1/agents/effectifs-genre?contrat_valide=1')
        ->assertOk()
        ->assertExactJson(['data' => ['hommes' => 2, 'femmes' => 2, 'non_renseigne' => 1]]);
});

it('n’inclut pas l’administration dans l’effectif du rôle opération', function () {
    ($this->creerAgent)('madame', 'A-1', ['type' => TypeAgent::Administration]);
    $operation = User::factory()->create();
    $operation->assignRole('operation');

    $this->actingAs($operation)
        ->getJson('/api/v1/agents/effectifs-genre')
        ->assertOk()
        ->assertJsonPath('data.femmes', 2);
});

it('refuse l’effectif sans le droit de voir le personnel', function () {
    $this->actingAs(User::factory()->create())
        ->getJson('/api/v1/agents/effectifs-genre')
        ->assertForbidden();
});
