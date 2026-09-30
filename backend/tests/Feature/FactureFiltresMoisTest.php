<?php

use App\Domain\Shared\Enums\StatutClient;
use App\Domain\Shared\Enums\StatutFacture;
use App\Domain\Shared\Enums\TypeClient;
use App\Models\Client;
use App\Models\Facture;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->commercial = User::factory()->create([
        'email' => 'com-factures-mois@sis.ci',
        'password' => Hash::make('password'),
    ]);
    $this->commercial->assignRole('commercial');

    $this->client = Client::query()->create([
        'type' => TypeClient::Entreprise,
        'raison_sociale' => 'Client Filtres Facture',
        'telephone' => '0700112233',
        'statut' => StatutClient::Actif,
    ]);

    Facture::query()->create([
        'client_id' => $this->client->id,
        'numero' => 'SC-ABJ-2026-0001',
        'date_emission' => '2026-01-15',
        'montant_ht' => 100000,
        'montant_tva' => 18000,
        'montant_ttc' => 118000,
        'statut' => StatutFacture::Valide,
    ]);

    Facture::query()->create([
        'client_id' => $this->client->id,
        'numero' => 'SC-ABJ-2026-0002',
        'date_emission' => '2026-02-10',
        'montant_ht' => 50000,
        'montant_tva' => 9000,
        'montant_ttc' => 59000,
        'statut' => StatutFacture::Valide,
    ]);

    Facture::query()->create([
        'client_id' => $this->client->id,
        'numero' => 'SC-ABJ-2025-0099',
        'date_emission' => '2025-01-20',
        'montant_ht' => 20000,
        'montant_tva' => 0,
        'montant_ttc' => 20000,
        'statut' => StatutFacture::Valide,
    ]);
Facture::query()->create([
        'client_id' => $this->client->id,
        'numero' => 'SC-ABJ-2026-0031',
        'date_emission' => '2026-01-31',
        'montant_ht' => 10000,
        'montant_tva' => 0,
        'montant_ttc' => 10000,
        'statut' => StatutFacture::Valide,
    ]);
});

it('filtre les factures par mois et année sur date_emission', function () {
    $response = $this->actingAs($this->commercial)
        ->getJson('/api/v1/factures?mois=1&annee=2026')
        ->assertOk();

    $numeros = collect($response->json('data'))->pluck('numero');

    expect($numeros)->toContain('SC-ABJ-2026-0001', 'SC-ABJ-2026-0031')
        ->and($numeros)->not->toContain('SC-ABJ-2026-0002')
        ->and($numeros)->not->toContain('SC-ABJ-2025-0099');
});

it('filtre les factures par année seule', function () {
    $response = $this->actingAs($this->commercial)
        ->getJson('/api/v1/factures?annee=2026')
        ->assertOk();

    $numeros = collect($response->json('data'))->pluck('numero');

    expect($numeros)->toContain('SC-ABJ-2026-0001', 'SC-ABJ-2026-0002')
        ->and($numeros)->not->toContain('SC-ABJ-2025-0099');
});

it('ignore le mois sans année', function () {
    $response = $this->actingAs($this->commercial)
        ->getJson('/api/v1/factures?mois=1')
        ->assertOk();

    expect(collect($response->json('data'))->pluck('numero'))
        ->toHaveCount(4);
});
