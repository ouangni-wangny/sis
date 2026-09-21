<?php

use App\Models\Client;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->admin = User::factory()->create([
        'email' => 'admin@sis.ci',
        'password' => Hash::make('password'),
    ]);
    $this->admin->assignRole('super-admin');
});

it('creates a client', function () {
    $response = $this->actingAs($this->admin)->postJson('/api/v1/clients', [
        'type' => 'entreprise',
        'raison_sociale' => 'SOCIETE TEST CI',
        'telephone' => '+22507000000',
        'statut' => 'actif',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.raison_sociale', 'SOCIETE TEST CI');

    expect(Client::query()->count())->toBe(1);
});

it('validates client creation', function () {
    $this->actingAs($this->admin)
        ->postJson('/api/v1/clients', [])
        ->assertStatus(422);
});

it('lists clients', function () {
    Client::query()->create([
        'type' => 'entreprise',
        'raison_sociale' => 'Client A',
        'statut' => 'actif',
    ]);

    $this->actingAs($this->admin)
        ->getJson('/api/v1/clients')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('soft deletes client with cascade on related data', function () {
    $client = Client::query()->create([
        'type' => 'entreprise',
        'raison_sociale' => 'Client Cascade',
        'statut' => 'actif',
    ]);

    $zone = App\Models\Zone::query()->create([
        'nom' => 'Zone Test',
    ]);

    $site = App\Models\Site::query()->create([
        'client_id' => $client->id,
        'zone_id' => $zone->id,
        'nom' => 'Site Test',
    ]);

    $poste = App\Models\Poste::query()->create([
        'site_id' => $site->id,
        'nom' => 'Poste Test',
        'agents_requis' => 1,
    ]);

    $abonnement = App\Models\Abonnement::query()->create([
        'client_id' => $client->id,
        'offre_id' => App\Models\Offre::query()->create([
            'libelle' => 'Offre Test',
            'prix_mensuel' => 1000,
            'actif' => true,
        ])->id,
        'date_debut' => now()->toDateString(),
        'statut' => 'actif',
    ]);

    $this->actingAs($this->admin)
        ->deleteJson("/api/v1/clients/{$client->id}")
        ->assertNoContent();

    expect(Client::withTrashed()->find($client->id)?->trashed())->toBeTrue()
        ->and(App\Models\Site::withTrashed()->find($site->id)?->trashed())->toBeTrue()
        ->and(App\Models\Poste::withTrashed()->find($poste->id)?->trashed())->toBeTrue()
        ->and(App\Models\Abonnement::withTrashed()->find($abonnement->id)?->trashed())->toBeTrue()
        ->and(Client::query()->find($client->id))->toBeNull()
        ->and(App\Models\Site::query()->find($site->id))->toBeNull();
});
