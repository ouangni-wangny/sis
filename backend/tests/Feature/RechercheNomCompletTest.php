<?php

use App\Domain\Shared\Enums\TypeAgent;
use App\Models\Absence;
use App\Models\Agent;
use App\Models\Contrat;
use App\Models\Grade;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->admin = User::factory()->create(['nom' => 'Traore', 'prenom' => 'Issa Moussa']);
    $this->admin->assignRole('super-admin');

    $grade = Grade::query()->create(['libelle' => 'Agent', 'type_agent' => TypeAgent::Agent]);

    $this->creer = function (string $prenom, string $nom, string $matricule) use ($grade): Agent {
        $agent = Agent::query()->create([
            'grade_id' => $grade->id,
            'type' => TypeAgent::Agent,
            'nom' => $nom,
            'prenom' => $prenom,
            'matricule' => $matricule,
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

    $this->richard = ($this->creer)('Kouame Richard', 'Koffi', 'R-1');
    $this->awa = ($this->creer)('Awa', 'Kone', 'A-1');

    $this->recherche = fn (string $url, string $q) => collect(
        $this->actingAs($this->admin)->getJson($url.'?q='.urlencode($q))->assertOk()->json('data')
    );
});

it('trouve un agent par prénom et nom, dans les deux sens', function (string $q) {
    expect(($this->recherche)('/api/v1/agents', $q)->pluck('matricule')->all())->toBe(['R-1']);
})->with([
    'prénom puis nom' => 'Richard Koffi',
    'nom puis prénom' => 'Koffi Richard',
    'nom complet' => 'Kouame Richard Koffi',
    'espaces multiples' => '  Richard   Koffi ',
    'casse différente' => 'richard koffi',
]);

it('continue de trouver sur un seul mot, le prénom composé et le matricule', function (string $q) {
    expect(($this->recherche)('/api/v1/agents', $q)->pluck('matricule')->all())->toBe(['R-1']);
})->with(['Koffi', 'Richard', 'Kouame Richard', 'R-1']);

it('exige que tous les mots correspondent', function () {
    expect(($this->recherche)('/api/v1/agents', 'Richard Kone'))->toBeEmpty();
});

it('ne mélange pas les mots de deux agents différents', function () {
    expect(($this->recherche)('/api/v1/agents', 'Awa Koffi'))->toBeEmpty();
});

it('garde les filtres de la liste quand la recherche a plusieurs mots', function () {
    $this->actingAs($this->admin)
        ->getJson('/api/v1/agents?q=Richard+Koffi&statut=archive')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('s’applique à la recherche des contrats', function () {
    $lignes = ($this->recherche)('/api/v1/contrats', 'Richard Koffi');

    expect($lignes->pluck('agent_id')->all())->toBe([$this->richard->id]);
});

it('s’applique à la recherche des absences', function () {
    Absence::query()->create([
        'agent_id' => $this->richard->id,
        'type' => 'conge',
        'date_debut' => '2026-03-01',
        'date_fin' => '2026-03-02',
        'statut' => 'en_attente',
    ]);
    Absence::query()->create([
        'agent_id' => $this->awa->id,
        'type' => 'conge',
        'date_debut' => '2026-03-01',
        'date_fin' => '2026-03-02',
        'statut' => 'en_attente',
    ]);

    expect(($this->recherche)('/api/v1/absences', 'Richard Koffi'))->toHaveCount(1);
});

it('s’applique à la recherche des utilisateurs', function () {
    expect(($this->recherche)('/api/v1/users', 'Moussa Traore')->pluck('id')->all())->toContain($this->admin->id);
});
