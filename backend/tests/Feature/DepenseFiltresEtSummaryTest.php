<?php

use App\Models\CategorieDepense;
use App\Models\CompteTresorerie;
use App\Models\Depense;
use App\Models\ModePaiementParam;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->comptable = User::factory()->create([
        'email' => 'comptable-depenses@sis.ci',
        'password' => Hash::make('password'),
    ]);
    $this->comptable->assignRole('comptable');

    ModePaiementParam::query()->firstOrCreate(
        ['code' => 'especes'],
        ['libelle' => 'Espèces', 'actif' => true, 'ordre' => 1],
    );

    $this->categorie = CategorieDepense::query()->create([
        'libelle' => 'Fournitures',
        'actif' => true,
    ]);

    $this->compte = CompteTresorerie::query()->create([
        'libelle' => 'Caisse dépenses',
        'type' => 'caisse',
        'solde_ouverture' => 1_000_000,
        'actif' => true,
    ]);

    Depense::query()->create([
        'categorie_depense_id' => $this->categorie->id,
        'libelle' => 'Janvier A',
        'montant' => 10000,
        'date_depense' => '2026-01-05',
        'compte_tresorerie_id' => $this->compte->id,
        'mode' => 'especes',
        'reference' => 'DEP-JAN-A',
        'statut' => 'validee',
        'user_id' => $this->comptable->id,
    ]);

    Depense::query()->create([
        'categorie_depense_id' => $this->categorie->id,
        'libelle' => 'Janvier B',
        'montant' => 25000,
        'date_depense' => '2026-01-20',
        'compte_tresorerie_id' => $this->compte->id,
        'mode' => 'especes',
        'reference' => 'DEP-JAN-B',
        'statut' => 'validee',
        'user_id' => $this->comptable->id,
    ]);

    Depense::query()->create([
        'categorie_depense_id' => $this->categorie->id,
        'libelle' => 'Février',
        'montant' => 50000,
        'date_depense' => '2026-02-03',
        'compte_tresorerie_id' => $this->compte->id,
        'mode' => 'especes',
        'reference' => 'DEP-FEV',
        'statut' => 'validee',
        'user_id' => $this->comptable->id,
    ]);

    Depense::query()->create([
        'categorie_depense_id' => $this->categorie->id,
        'libelle' => 'Janvier annulée',
        'montant' => 99999,
        'date_depense' => '2026-01-12',
        'compte_tresorerie_id' => $this->compte->id,
        'mode' => 'especes',
        'reference' => 'DEP-JAN-ANN',
        'statut' => 'annulee',
        'user_id' => $this->comptable->id,
    ]);
});

it('filtre les dépenses par mois et expose le total des validées', function () {
    $response = $this->actingAs($this->comptable)
        ->getJson('/api/v1/depenses?mois=1&annee=2026')
        ->assertOk();

    $libelles = collect($response->json('data'))->pluck('libelle');

    expect($libelles)->toContain('Janvier A', 'Janvier B', 'Janvier annulée')
        ->and($libelles)->not->toContain('Février')
        ->and((float) $response->json('summary.total_montant'))->toBe(35000.0);
});

it('n’expose plus de route de suppression de dépense', function () {
    $depense = Depense::query()->where('reference', 'DEP-JAN-A')->firstOrFail();

    $this->actingAs($this->comptable)
        ->deleteJson("/api/v1/depenses/{$depense->id}")
        ->assertMethodNotAllowed();
});
