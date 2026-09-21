<?php

use App\Domain\Shared\Enums\StatutRonde;
use App\Domain\Shared\Enums\TypeAgent;
use App\Models\Anomalie;
use App\Models\Checkpoint;
use App\Models\Client;
use App\Models\Controle;
use App\Models\Ronde;
use App\Models\RondierPerimetre;
use App\Models\Site;
use App\Models\User;
use App\Models\Vacation;
use App\Models\Zone;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    // Fige l'heure à l'intérieur du créneau de la vacation (08h-16h) créée
    // ci-dessous, pour que le test ne dépende pas de l'heure d'exécution.
    Carbon::setTestNow(Carbon::now()->setTime(10, 0));
    $this->seed(RolePermissionSeeder::class);

    $client = Client::query()->create([
        'type' => 'entreprise',
        'raison_sociale' => 'Client Sync',
        'statut' => 'actif',
    ]);
    $zone = Zone::query()->create(['nom' => 'Zone Sync']);
    $this->site = Site::query()->create([
        'client_id' => $client->id,
        'zone_id' => $zone->id,
        'nom' => 'Site Sync',
        'latitude' => 5.35,
        'longitude' => -4.01,
        'rayon_metres' => 200,
    ]);

    $this->controleur = makeMobileAgent('RD-SYNC-OWNER', 'controleur');
    $this->posted = makeMobileAgent('AG-SYNC-POSTED', 'agent');
    $this->other = makeMobileAgent('AG-SYNC-OTHER', 'agent');

    // Align agent types with roles
    $this->controleur->update(['type' => TypeAgent::Controleur]);
    $this->posted->update(['type' => TypeAgent::Agent]);
    $this->other->update(['type' => TypeAgent::Agent]);

    Vacation::query()->create([
        'agent_id' => $this->posted->id,
        'site_id' => $this->site->id,
        'date_debut' => now()->toDateString(),
        'heure_debut' => '08:00',
        'heure_fin' => '16:00',
        'statut' => 'planifiee',
    ]);

    RondierPerimetre::query()->create([
        'agent_id' => $this->controleur->id,
        'zone_id' => $zone->id,
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('forces controle agent_id to the authenticated rondier even if a different agent_id is sent', function () {
    Sanctum::actingAs($this->controleur->user, ['mobile']);

    $clientUuid = (string) Str::uuid();

    $this->postJson('/api/v1/mobile/sync', [
        'controles' => [[
            'client_uuid' => $clientUuid,
            'agent_id' => $this->other->id,
            'controle_agent_id' => $this->posted->id,
            'site_id' => $this->site->id,
            'latitude' => 5.35,
            'longitude' => -4.01,
            'commentaire' => 'Présent au poste',
        ]],
    ])->assertOk();

    $controle = Controle::query()->where('client_uuid', $clientUuid)->first();

    expect($controle)->not->toBeNull();
    expect($controle->agent_id)->toBe($this->controleur->id);
    expect($controle->controle_agent_id)->toBe($this->posted->id);
});

it('forces anomalie signale_par_id to the authenticated agent even if a different id is sent', function () {
    Sanctum::actingAs($this->controleur->user, ['mobile']);

    $clientUuid = (string) Str::uuid();

    $this->postJson('/api/v1/mobile/sync', [
        'anomalies' => [[
            'client_uuid' => $clientUuid,
            'signale_par_id' => $this->other->id,
            'site_id' => $this->site->id,
            'type' => 'technique',
            'commentaire' => 'Lampe cassée',
        ]],
    ])->assertOk();

    $anomalie = Anomalie::query()->where('client_uuid', $clientUuid)->first();

    expect($anomalie)->not->toBeNull();
    expect($anomalie->signale_par_id)->toBe($this->controleur->id);
});

it('rejects mobile sync when authenticated user has no linked agent', function () {
    $backoffice = User::factory()->create();
    $backoffice->assignRole('operation');
    Sanctum::actingAs($backoffice, ['backoffice']);

    $this->postJson('/api/v1/mobile/sync', [
        'controles' => [[
            'client_uuid' => (string) Str::uuid(),
            'controle_agent_id' => $this->posted->id,
            'site_id' => $this->site->id,
            'latitude' => 5.35,
            'longitude' => -4.01,
        ]],
    ])->assertStatus(403);
});

it('refuses to sync ronde scans for a ronde belonging to another agent', function () {
    $ronde = Ronde::query()->create([
        'agent_id' => $this->other->id,
        'site_id' => $this->site->id,
        'statut' => StatutRonde::EnCours,
        'progression' => 0,
        'demarree_at' => now(),
    ]);

    $checkpoint = Checkpoint::query()->create([
        'site_id' => $this->site->id,
        'nom' => 'Checkpoint sync',
        'code_qr' => 'QR-SYNC-01',
        'latitude' => 5.32,
        'longitude' => -4.01,
        'ordre' => 1,
    ]);

    Sanctum::actingAs($this->controleur->user, ['mobile']);

    $this->postJson('/api/v1/mobile/sync', [
        'ronde_scans' => [[
            'ronde_id' => $ronde->id,
            'checkpoint_id' => $checkpoint->id,
            'latitude' => 5.32,
            'longitude' => -4.01,
            'code_qr' => 'QR-SYNC-01',
        ]],
    ])->assertStatus(403);
});
