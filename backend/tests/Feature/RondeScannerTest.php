<?php

use App\Domain\Shared\Enums\StatutRonde;
use App\Models\Checkpoint;
use App\Models\Client;
use App\Models\Ronde;
use App\Models\Site;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->operation = User::factory()->create([
        'email' => 'operation@sis.ci',
        'password' => Hash::make('password'),
    ]);
    $this->operation->assignRole('operation');

    $client = Client::query()->create([
        'type' => 'entreprise',
        'raison_sociale' => 'Client Ronde',
        'statut' => 'actif',
    ]);
    $zone = Zone::query()->create(['nom' => 'Zone Ronde']);
    $this->site = Site::query()->create([
        'client_id' => $client->id,
        'zone_id' => $zone->id,
        'nom' => 'Site Ronde',
        'latitude' => 5.3203570,
        'longitude' => -4.0161070,
        'rayon_metres' => 200,
    ]);

    $this->checkpoint = Checkpoint::query()->create([
        'site_id' => $this->site->id,
        'nom' => 'Entrée',
        'code_qr' => 'QR-TEST-01',
        'latitude' => 5.3203570,
        'longitude' => -4.0161070,
        'ordre' => 1,
    ]);

    $this->owner = makeMobileAgent('AG-OWNER', 'agent');
    $this->other = makeMobileAgent('AG-OTHER', 'agent');

    $this->ronde = Ronde::query()->create([
        'agent_id' => $this->owner->id,
        'site_id' => $this->site->id,
        'statut' => StatutRonde::EnCours,
        'progression' => 0,
        'demarree_at' => now(),
    ]);
});

it('rejects scanning without GPS coordinates', function () {
    Sanctum::actingAs($this->owner->user, ['mobile']);

    $this->postJson("/api/v1/rondes/{$this->ronde->id}/scanner", [
        'checkpoint_id' => $this->checkpoint->id,
        'code_qr' => 'QR-TEST-01',
    ])->assertStatus(422);
});

it('rejects scanning without a QR code', function () {
    Sanctum::actingAs($this->owner->user, ['mobile']);

    $this->postJson("/api/v1/rondes/{$this->ronde->id}/scanner", [
        'checkpoint_id' => $this->checkpoint->id,
        'latitude' => 5.3203570,
        'longitude' => -4.0161070,
    ])->assertStatus(422);
});

it('rejects scanning with a mismatched QR code', function () {
    Sanctum::actingAs($this->owner->user, ['mobile']);

    $this->postJson("/api/v1/rondes/{$this->ronde->id}/scanner", [
        'checkpoint_id' => $this->checkpoint->id,
        'latitude' => 5.3203570,
        'longitude' => -4.0161070,
        'code_qr' => 'QR-WRONG',
    ])->assertStatus(422)
        ->assertJsonPath('error', 'DomainException');
});

it('accepts a valid scan with matching GPS and QR code', function () {
    Sanctum::actingAs($this->owner->user, ['mobile']);

    $this->postJson("/api/v1/rondes/{$this->ronde->id}/scanner", [
        'checkpoint_id' => $this->checkpoint->id,
        'latitude' => 5.3203570,
        'longitude' => -4.0161070,
        'code_qr' => 'QR-TEST-01',
    ])->assertOk()
        ->assertJsonPath('data.ronde_checkpoints.0.valide', true);
});

it('forbids an agent from scanning a ronde they do not own', function () {
    Sanctum::actingAs($this->other->user, ['mobile']);

    $this->postJson("/api/v1/rondes/{$this->ronde->id}/scanner", [
        'checkpoint_id' => $this->checkpoint->id,
        'latitude' => 5.3203570,
        'longitude' => -4.0161070,
        'code_qr' => 'QR-TEST-01',
    ])->assertStatus(403);
});

it('allows an operation user with rondes.manage to scan any ronde', function () {
    $this->actingAs($this->operation)
        ->postJson("/api/v1/rondes/{$this->ronde->id}/scanner", [
            'checkpoint_id' => $this->checkpoint->id,
            'latitude' => 5.3203570,
            'longitude' => -4.0161070,
            'code_qr' => 'QR-TEST-01',
        ])->assertOk();
});
