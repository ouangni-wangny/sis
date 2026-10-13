<?php

use App\Application\Commercial\UpsertPaiementAction;
use App\Domain\Shared\Enums\DirectionMouvementTresorerie;
use App\Domain\Shared\Enums\SourceMouvementTresorerie;
use App\Domain\Shared\Enums\StatutClient;
use App\Domain\Shared\Enums\StatutFacture;
use App\Domain\Shared\Enums\TypeClient;
use App\Jobs\GenererFacturePdfJob;
use App\Models\Client;
use App\Models\CompteTresorerie;
use App\Models\Facture;
use App\Models\ModePaiementParam;
use App\Models\MouvementTresorerie;
use App\Models\Paiement;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Bus::fake([GenererFacturePdfJob::class]);

    ModePaiementParam::query()->firstOrCreate(
        ['code' => 'virement'],
        ['libelle' => 'Virement', 'actif' => true, 'ordre' => 1],
    );

    $this->admin = User::factory()->create([
        'email' => 'admin-annule-facture@sis.ci',
        'password' => Hash::make('password'),
    ]);
    $this->admin->assignRole('super-admin');

    $this->commercial = User::factory()->create([
        'email' => 'com-annule-facture@sis.ci',
        'password' => Hash::make('password'),
    ]);
    $this->commercial->assignRole('commercial');

    $this->compte = CompteTresorerie::query()->create([
        'libelle' => 'Banque annulation',
        'type' => 'banque',
        'solde_ouverture' => 0,
        'actif' => true,
    ]);

    $this->client = Client::query()->create([
        'type' => TypeClient::Entreprise,
        'raison_sociale' => 'Client Annulation',
        'statut' => StatutClient::Actif,
    ]);
});

function makeFactureValideePourAnnulation(Client $client, string $numero = 'SC-ABJ-2026-ANN1'): Facture
{
    return Facture::query()->create([
        'client_id' => $client->id,
        'numero' => $numero,
        'date_emission' => '2026-10-01',
        'montant_ht' => 100000,
        'montant_tva' => 18000,
        'montant_ttc' => 118000,
        'statut' => StatutFacture::Valide,
    ]);
}

it('autorise le commercial à rejeter une proforma', function () {
    $facture = Facture::query()->create([
        'client_id' => $this->client->id,
        'numero' => 'SC-ABJ-2026-PRO-R',
        'date_emission' => '2026-10-01',
        'montant_ht' => 50000,
        'montant_tva' => 0,
        'montant_ttc' => 50000,
        'statut' => StatutFacture::EnAttente,
    ]);

    $this->actingAs($this->commercial)
        ->patchJson("/api/v1/factures/{$facture->id}/statut", ['statut' => 'rejetee'])
        ->assertOk()
        ->assertJsonPath('data.statut', 'rejetee');
});

it('refuse au commercial d’annuler une facture validée', function () {
    $facture = makeFactureValideePourAnnulation($this->client);

    $this->actingAs($this->commercial)
        ->patchJson("/api/v1/factures/{$facture->id}/statut", ['statut' => 'annulee'])
        ->assertForbidden();

    expect($facture->fresh()->statut)->toBe(StatutFacture::Valide);
});

it('refuse de rejeter une facture déjà validée', function () {
    $facture = makeFactureValideePourAnnulation($this->client, 'SC-ABJ-2026-NO-REJ');

    $this->actingAs($this->commercial)
        ->patchJson("/api/v1/factures/{$facture->id}/statut", ['statut' => 'rejetee'])
        ->assertStatus(422);

    expect($facture->fresh()->statut)->toBe(StatutFacture::Valide);
});

it('permet au super-admin d’annuler une facture payée et contrepasser la trésorerie', function () {
    $facture = makeFactureValideePourAnnulation($this->client, 'SC-ABJ-2026-ANN-PAY');

    $this->actingAs($this->admin);
    $paiement = app(UpsertPaiementAction::class)->create([
        'facture_id' => $facture->id,
        'montant' => 118000,
        'date_paiement' => '2026-10-05',
        'mode' => 'virement',
        'compte_tresorerie_id' => $this->compte->id,
        'reference' => 'ENC-ANN-1',
    ]);

    expect($this->compte->soldeCourant())->toBe(118000.0);

    $this->actingAs($this->admin)
        ->patchJson("/api/v1/factures/{$facture->id}/statut", ['statut' => 'annulee'])
        ->assertOk()
        ->assertJsonPath('data.statut', 'annulee');

    expect($facture->fresh()->statut)->toBe(StatutFacture::Annulee)
        ->and(Paiement::withTrashed()->find($paiement->id)?->trashed())->toBeTrue()
        ->and(Paiement::query()->where('facture_id', $facture->id)->count())->toBe(0)
        ->and($this->compte->soldeCourant())->toBe(0.0);

    $entree = MouvementTresorerie::query()
        ->where('source_type', SourceMouvementTresorerie::FacturePaiement)
        ->where('source_id', $paiement->id)
        ->where('direction', DirectionMouvementTresorerie::Entree)
        ->first();

    $inverse = MouvementTresorerie::query()
        ->where('source_type', SourceMouvementTresorerie::Ajustement)
        ->where('direction', DirectionMouvementTresorerie::Sortie)
        ->where('montant', 118000)
        ->where('notes', 'like', 'Annulation facture%')
        ->first();

    expect($entree)->not->toBeNull()
        ->and($inverse)->not->toBeNull();
});
