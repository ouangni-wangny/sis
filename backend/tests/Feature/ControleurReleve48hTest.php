<?php

use App\Domain\Shared\Enums\TypeAgent;
use App\Models\Agent;
use App\Models\Grade;
use App\Models\RondierPerimetre;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->admin = User::factory()->create([
        'email' => 'admin-releve48@sis.ci',
        'password' => Hash::make('password'),
    ]);
    $this->admin->assignRole('super-admin');

    $grade = Grade::query()->create([
        'libelle' => 'Contrôleur Relève',
        'type_agent' => TypeAgent::Controleur,
    ]);

    $this->c1 = Agent::query()->create([
        'grade_id' => $grade->id,
        'type' => TypeAgent::Controleur,
        'nom' => 'Alpha',
        'prenom' => 'Ctrl',
        'matricule' => 'RD-REL-1',
        'statut' => 'disponible',
    ]);

    $this->c2 = Agent::query()->create([
        'grade_id' => $grade->id,
        'type' => TypeAgent::Controleur,
        'nom' => 'Beta',
        'prenom' => 'Ctrl',
        'matricule' => 'RD-REL-2',
        'statut' => 'disponible',
    ]);

    $this->c3 = Agent::query()->create([
        'grade_id' => $grade->id,
        'type' => TypeAgent::Controleur,
        'nom' => 'Gamma',
        'prenom' => 'Ctrl',
        'matricule' => 'RD-REL-3',
        'statut' => 'disponible',
    ]);

    $this->zone = Zone::query()->create(['nom' => 'Zone Relève 48h']);
});

it('assigne un contrôleur seul sans indice de relève', function () {
    $this->actingAs($this->admin)->putJson(
        '/api/v1/agents/'.$this->c1->id.'/perimetre',
        ['items' => [['zone_id' => $this->zone->id]]]
    )->assertOk();

    $p = RondierPerimetre::query()->where('agent_id', $this->c1->id)->first();
    expect($p)->not->toBeNull()
        ->and($p->indice_releve)->toBeNull()
        ->and($p->releve_depuis)->toBeNull();
});

it('forme un binôme 48h avec indices 0 et 1', function () {
    $this->actingAs($this->admin)->putJson(
        '/api/v1/agents/'.$this->c1->id.'/perimetre',
        ['items' => [['zone_id' => $this->zone->id]]]
    )->assertOk();

    $this->actingAs($this->admin)->putJson(
        '/api/v1/agents/'.$this->c2->id.'/perimetre',
        ['items' => [['zone_id' => $this->zone->id]]]
    )->assertOk();

    $p1 = RondierPerimetre::query()->where('agent_id', $this->c1->id)->first();
    $p2 = RondierPerimetre::query()->where('agent_id', $this->c2->id)->first();

    expect($p1->indice_releve)->toBe(0)
        ->and($p2->indice_releve)->toBe(1)
        ->and($p1->releve_depuis)->not->toBeNull()
        ->and($p2->releve_depuis?->toDateString())->toBe($p1->releve_depuis->toDateString());
});

it('refuse un 3e contrôleur sur la même zone', function () {
    $this->actingAs($this->admin)->putJson(
        '/api/v1/agents/'.$this->c1->id.'/perimetre',
        ['items' => [['zone_id' => $this->zone->id]]]
    )->assertOk();

    $this->actingAs($this->admin)->putJson(
        '/api/v1/agents/'.$this->c2->id.'/perimetre',
        ['items' => [['zone_id' => $this->zone->id]]]
    )->assertOk();

    $this->actingAs($this->admin)->putJson(
        '/api/v1/agents/'.$this->c3->id.'/perimetre',
        ['items' => [['zone_id' => $this->zone->id]]]
    )->assertStatus(422);
});
