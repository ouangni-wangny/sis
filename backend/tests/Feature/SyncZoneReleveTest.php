<?php

use App\Domain\Shared\Enums\TypeAgent;
use App\Domain\Shared\Support\ControleurReleve48h;
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
        'email' => 'admin-zone-releve@sis.ci',
        'password' => Hash::make('password'),
    ]);
    $this->admin->assignRole('super-admin');

    $grade = Grade::query()->create([
        'libelle' => 'Contrôleur Relève Plan',
        'type_agent' => TypeAgent::Controleur,
    ]);

    $this->c1 = Agent::query()->create([
        'grade_id' => $grade->id,
        'type' => TypeAgent::Controleur,
        'nom' => 'Premier',
        'prenom' => 'Ctrl',
        'matricule' => 'ZR-1',
        'statut' => 'disponible',
    ]);
    $this->c2 = Agent::query()->create([
        'grade_id' => $grade->id,
        'type' => TypeAgent::Controleur,
        'nom' => 'Second',
        'prenom' => 'Ctrl',
        'matricule' => 'ZR-2',
        'statut' => 'disponible',
    ]);
    $this->zone = Zone::query()->create(['nom' => 'Zone Planning Relève']);
});

it('refuse la relève sans binôme', function () {
    $this->actingAs($this->admin)->putJson(
        '/api/v1/zones/'.$this->zone->id.'/releve',
        [
            'releve_depuis' => '2026-07-27',
            'releve_jusque' => '2026-08-23',
        ]
    )->assertStatus(422);
});

it('définit le début de cycle et l’ordre des indices', function () {
    $this->actingAs($this->admin)->putJson(
        '/api/v1/zones/'.$this->zone->id.'/controleurs',
        ['agent_ids' => [$this->c1->id, $this->c2->id]]
    )->assertOk();

    $this->actingAs($this->admin)->putJson(
        '/api/v1/zones/'.$this->zone->id.'/releve',
        [
            'releve_depuis' => '2026-07-27',
            'releve_jusque' => '2026-08-23',
            'ordre_agent_ids' => [$this->c2->id, $this->c1->id],
        ]
    )->assertOk()
        ->assertJsonPath('data.controleurs.0.id', $this->c2->id)
        ->assertJsonPath('data.controleurs.1.id', $this->c1->id);

    expect(RondierPerimetre::query()->where('agent_id', $this->c2->id)->value('indice_releve'))->toBe(0)
        ->and(RondierPerimetre::query()->where('agent_id', $this->c1->id)->value('indice_releve'))->toBe(1)
        ->and(RondierPerimetre::query()->where('zone_id', $this->zone->id)->value('releve_depuis')?->toDateString())->toBe('2026-07-27')
        ->and(RondierPerimetre::query()->where('zone_id', $this->zone->id)->value('releve_jusque')?->toDateString())->toBe('2026-08-23');

    expect(ControleurReleve48h::estEnService(0, '2026-07-27', '2026-07-27'))->toBeTrue()
        ->and(ControleurReleve48h::estEnService(1, '2026-07-27', '2026-07-27'))->toBeFalse()
        ->and(ControleurReleve48h::estEnService(1, '2026-07-27', '2026-07-29'))->toBeTrue();
});
