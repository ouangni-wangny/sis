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
        'email' => 'admin-zone-ctrl@sis.ci',
        'password' => Hash::make('password'),
    ]);
    $this->admin->assignRole('super-admin');

    $grade = Grade::query()->create([
        'libelle' => 'Contrôleur Zone',
        'type_agent' => TypeAgent::Controleur,
    ]);

    $this->c1 = Agent::query()->create([
        'grade_id' => $grade->id,
        'type' => TypeAgent::Controleur,
        'nom' => 'Un',
        'prenom' => 'Ctrl',
        'matricule' => 'ZC-1',
        'statut' => 'disponible',
    ]);
    $this->c2 = Agent::query()->create([
        'grade_id' => $grade->id,
        'type' => TypeAgent::Controleur,
        'nom' => 'Deux',
        'prenom' => 'Ctrl',
        'matricule' => 'ZC-2',
        'statut' => 'disponible',
    ]);
    $this->zone = Zone::query()->create(['nom' => 'Zone Sync Controllers']);
});

it('assigne deux contrôleurs à une zone via l’API zone', function () {
    $this->actingAs($this->admin)->putJson(
        '/api/v1/zones/'.$this->zone->id.'/controleurs',
        ['agent_ids' => [$this->c1->id, $this->c2->id]]
    )->assertOk()
        ->assertJsonCount(2, 'data.controleurs');

    expect(RondierPerimetre::query()->where('zone_id', $this->zone->id)->count())->toBe(2)
        ->and($this->c1->fresh()->statut->value)->toBe('en_activite')
        ->and($this->c2->fresh()->statut->value)->toBe('en_activite');

    $indices = RondierPerimetre::query()
        ->where('zone_id', $this->zone->id)
        ->pluck('indice_releve')
        ->sort()
        ->values()
        ->all();
    expect($indices)->toBe([0, 1]);
});

it('libère un contrôleur retiré de la zone', function () {
    $this->actingAs($this->admin)->putJson(
        '/api/v1/zones/'.$this->zone->id.'/controleurs',
        ['agent_ids' => [$this->c1->id, $this->c2->id]]
    )->assertOk();

    $this->actingAs($this->admin)->putJson(
        '/api/v1/zones/'.$this->zone->id.'/controleurs',
        ['agent_ids' => [$this->c1->id]]
    )->assertOk()
        ->assertJsonCount(1, 'data.controleurs');

    expect(RondierPerimetre::query()->where('agent_id', $this->c2->id)->exists())->toBeFalse()
        ->and($this->c2->fresh()->statut->value)->toBe('disponible')
        ->and(RondierPerimetre::query()->where('agent_id', $this->c1->id)->value('indice_releve'))->toBeNull();
});
