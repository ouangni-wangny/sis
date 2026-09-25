<?php

use App\Models\CompteTresorerie;
use App\Models\MouvementTresorerie;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('super-admin');

    $this->banque = CompteTresorerie::query()->create([
        'libelle' => 'Banque',
        'type' => 'banque',
        'solde_ouverture' => 500000,
    ]);
    $this->caisse = CompteTresorerie::query()->create([
        'libelle' => 'Caisse',
        'type' => 'caisse',
        'solde_ouverture' => 10000,
    ]);
});

it('transfère un montant entre deux comptes via deux mouvements liés', function () {
    $this->actingAs($this->admin)
        ->postJson('/api/v1/tresorerie/transferts', [
            'compte_source_id' => $this->banque->id,
            'compte_destination_id' => $this->caisse->id,
            'montant' => 150000,
            'date_mouvement' => '2026-09-25',
        ])
        ->assertCreated()
        ->assertJsonPath('data.sortie.direction', 'sortie')
        ->assertJsonPath('data.entree.direction', 'entree');

    expect($this->banque->soldeCourant())->toBe(350000.0)
        ->and($this->caisse->soldeCourant())->toBe(160000.0);

    $mouvements = MouvementTresorerie::query()->where('source_type', 'transfert')->get();
    expect($mouvements)->toHaveCount(2)
        ->and($mouvements->pluck('source_id')->unique())->toHaveCount(1);
});

it('refuse un transfert supérieur au solde du compte source', function () {
    $this->actingAs($this->admin)
        ->postJson('/api/v1/tresorerie/transferts', [
            'compte_source_id' => $this->caisse->id,
            'compte_destination_id' => $this->banque->id,
            'montant' => 20000,
            'date_mouvement' => '2026-09-25',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('montant');

    expect(MouvementTresorerie::query()->count())->toBe(0);
});

it('refuse un transfert vers le même compte ou un compte inactif', function () {
    $this->actingAs($this->admin)
        ->postJson('/api/v1/tresorerie/transferts', [
            'compte_source_id' => $this->banque->id,
            'compte_destination_id' => $this->banque->id,
            'montant' => 1000,
            'date_mouvement' => '2026-09-25',
        ])
        ->assertJsonValidationErrors('compte_destination_id');

    $this->caisse->update(['actif' => false]);

    $this->actingAs($this->admin)
        ->postJson('/api/v1/tresorerie/transferts', [
            'compte_source_id' => $this->banque->id,
            'compte_destination_id' => $this->caisse->id,
            'montant' => 1000,
            'date_mouvement' => '2026-09-25',
        ])
        ->assertJsonValidationErrors('compte_destination_id');
});

it('exclut les transferts des entrées et sorties du mois', function () {
    $this->actingAs($this->admin)
        ->postJson('/api/v1/tresorerie/transferts', [
            'compte_source_id' => $this->banque->id,
            'compte_destination_id' => $this->caisse->id,
            'montant' => 50000,
            'date_mouvement' => '2026-09-25',
        ])
        ->assertCreated();

    $this->actingAs($this->admin)
        ->getJson('/api/v1/tresorerie/stats?mois=9&annee=2026')
        ->assertOk()
        ->assertJsonPath('data.entrees_mois.total', 0)
        ->assertJsonPath('data.sorties_mois.total', 0)
        ->assertJsonPath('data.solde_consolide', 510000);
});
