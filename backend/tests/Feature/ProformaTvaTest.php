<?php

use App\Jobs\GenererFacturePdfJob;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Queue::fake([GenererFacturePdfJob::class]);

    $this->commercial = User::factory()->create([
        'email' => 'com-proforma-tva@sis.ci',
        'password' => Hash::make('password'),
    ]);
    $this->commercial->assignRole('commercial');

    $this->payload = [
        'client_nom' => 'Prospect TVA',
        'client_adresse' => 'Abidjan',
        'delai_paiement_jours' => 30,
        'lignes' => [
            [
                'description' => 'Gardiennage',
                'quantite' => 1,
                'prix_unitaire' => 100000,
            ],
        ],
    ];
});

it('applique la TVA 18 % quand appliquer_tva est true', function () {
    $response = $this->actingAs($this->commercial)
        ->postJson('/api/v1/factures/proforma', [
            ...$this->payload,
            'appliquer_tva' => true,
        ])
        ->assertOk();

    expect((float) $response->json('data.taux_tva'))->toBe(18.0)
        ->and((float) $response->json('data.montant_ht'))->toBe(100000.0)
        ->and((float) $response->json('data.montant_tva'))->toBe(18000.0)
        ->and((float) $response->json('data.montant_ttc'))->toBe(118000.0);
});

it('exonère la TVA quand appliquer_tva est false', function () {
    $response = $this->actingAs($this->commercial)
        ->postJson('/api/v1/factures/proforma', [
            ...$this->payload,
            'appliquer_tva' => false,
        ])
        ->assertOk();

    expect((float) $response->json('data.taux_tva'))->toBe(0.0)
        ->and((float) $response->json('data.montant_ht'))->toBe(100000.0)
        ->and((float) $response->json('data.montant_tva'))->toBe(0.0)
        ->and((float) $response->json('data.montant_ttc'))->toBe(100000.0);
});

it('autorise le comptable à créer une proforma (droits commercial)', function () {
    $comptable = User::factory()->create([
        'email' => 'comptable-proforma@sis.ci',
        'password' => Hash::make('password'),
    ]);
    $comptable->assignRole('comptable');

    $this->actingAs($comptable)
        ->postJson('/api/v1/factures/proforma', [
            ...$this->payload,
            'appliquer_tva' => true,
        ])
        ->assertOk();
});
