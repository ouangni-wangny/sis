<?php

use App\Domain\Shared\Enums\DirectionMouvementTresorerie;
use App\Domain\Shared\Enums\SourceMouvementTresorerie;
use App\Domain\Shared\Enums\StatutClient;
use App\Domain\Shared\Enums\StatutFacture;
use App\Domain\Shared\Enums\TypeClient;
use App\Models\Client;
use App\Models\CompteTresorerie;
use App\Models\Facture;
use App\Models\MouvementTresorerie;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->comptable = User::factory()->create([
        'email' => 'comptable-stats-ca@sis.ci',
        'password' => Hash::make('password'),
    ]);
    $this->comptable->assignRole('comptable');

    $this->compte = CompteTresorerie::query()->create([
        'libelle' => 'Banque CA',
        'type' => 'banque',
        'solde_ouverture' => 0,
        'actif' => true,
    ]);

    $client = Client::query()->create([
        'type' => TypeClient::Entreprise,
        'raison_sociale' => 'Client CA',
        'statut' => StatutClient::Actif,
    ]);

    Facture::query()->create([
        'client_id' => $client->id,
        'numero' => 'SC-ABJ-2026-CA01',
        'date_emission' => '2026-01-31',
        'montant_ht' => 200000,
        'montant_tva' => 36000,
        'montant_ttc' => 236000,
        'statut' => StatutFacture::Valide,
    ]);

    Facture::query()->create([
        'client_id' => $client->id,
        'numero' => 'SC-ABJ-2026-CA02',
        'date_emission' => '2026-02-01',
        'montant_ht' => 50000,
        'montant_tva' => 9000,
        'montant_ttc' => 59000,
        'statut' => StatutFacture::Valide,
    ]);

    Facture::query()->create([
        'client_id' => $client->id,
        'numero' => 'SC-ABJ-2026-PRO',
        'date_emission' => '2026-01-15',
        'montant_ht' => 10000,
        'montant_tva' => 0,
        'montant_ttc' => 10000,
        'statut' => StatutFacture::EnAttente,
    ]);

    // Encaissement facture (entrée)
    MouvementTresorerie::query()->create([
        'compte_tresorerie_id' => $this->compte->id,
        'direction' => DirectionMouvementTresorerie::Entree,
        'montant' => 100000,
        'date_mouvement' => '2026-01-20',
        'mode' => 'virement',
        'source_type' => SourceMouvementTresorerie::FacturePaiement,
        'source_id' => null,
        'reference' => 'PAY-1',
        'user_id' => $this->comptable->id,
    ]);

    // Approvisionnement transfert : ne doit PAS compter dans entrees_mois
    MouvementTresorerie::query()->create([
        'compte_tresorerie_id' => $this->compte->id,
        'direction' => DirectionMouvementTresorerie::Approvisionnement,
        'montant' => 50000,
        'date_mouvement' => '2026-01-21',
        'mode' => 'virement',
        'source_type' => SourceMouvementTresorerie::Transfert,
        'source_id' => '00000000-0000-0000-0000-000000000001',
        'reference' => 'TRF-1',
        'user_id' => $this->comptable->id,
    ]);

    // Retour (ajustement) : ne doit PAS compter dans entrees_mois
    MouvementTresorerie::query()->create([
        'compte_tresorerie_id' => $this->compte->id,
        'direction' => DirectionMouvementTresorerie::Retour,
        'montant' => 8000,
        'date_mouvement' => '2026-01-22',
        'mode' => 'especes',
        'source_type' => SourceMouvementTresorerie::Ajustement,
        'source_id' => null,
        'reference' => 'AJ-1',
        'user_id' => $this->comptable->id,
    ]);
});

it('calcule le CA du mois sur les factures validées par date_emission', function () {
    $response = $this->actingAs($this->comptable)
        ->getJson('/api/v1/tresorerie/stats?mois=1&annee=2026')
        ->assertOk();

    expect((float) $response->json('data.chiffre_affaires_mois.total_ht'))->toBe(200000.0)
        ->and((float) $response->json('data.chiffre_affaires_mois.total_ttc'))->toBe(236000.0)
        ->and((int) $response->json('data.chiffre_affaires_mois.count'))->toBe(1);
});

it('compte les entrées uniquement sur les encaissements facture', function () {
    $response = $this->actingAs($this->comptable)
        ->getJson('/api/v1/tresorerie/stats?mois=1&annee=2026')
        ->assertOk();

    expect((float) $response->json('data.entrees_mois.total'))->toBe(100000.0)
        ->and((int) $response->json('data.entrees_mois.count'))->toBe(1)
        ->and((float) $response->json('data.retours_mois.total'))->toBe(8000.0);
});
