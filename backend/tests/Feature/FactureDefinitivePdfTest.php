<?php

/*
 * Retour client 5 — PDF : proforma vs facture définitive.
 */

use App\Domain\Shared\Enums\StatutFacture;
use App\Jobs\GenererFacturePdfJob;
use App\Models\Facture;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Hash;

function makeFacturePdf(StatutFacture $statut): Facture
{
    $facture = Facture::query()->create([
        'numero' => 'SC-ABJ-2026-0042',
        'date_emission' => '2026-10-09',
        'montant_ht' => 100000,
        'montant_tva' => 18000,
        'montant_ttc' => 118000,
        'taux_tva' => 18,
        'devise' => 'XOF',
        'statut' => $statut,
        'client_nom' => 'Client PDF',
        'conditions_paiement' => 'Paiement à 30 jours',
        'delai_validite' => '30 jours',
    ]);
    $facture->lignes()->create([
        'description' => 'Gardiennage',
        'quantite' => 1,
        'prix_unitaire' => 100000,
        'montant' => 100000,
        'ordre' => 1,
    ]);

    return $facture->fresh(['lignes', 'client']);
}

function renderFacturePdf(Facture $facture): string
{
    return view('pdf.facture', ['facture' => $facture, 'generatedAt' => '09/10/2026 10:00'])->render();
}

it('affiche les mentions proforma sur une facture en attente', function () {
    $html = renderFacturePdf(makeFacturePdf(StatutFacture::EnAttente));

    expect($html)->toContain('Facture pro forma')
        ->toContain('Arrêté le présent devis')
        ->toContain('abonnement mensuel sera de')
        ->toContain('Conditions de mise en place');
});

it('retire les mentions proforma sur une facture définitive', function () {
    $html = renderFacturePdf(makeFacturePdf(StatutFacture::Valide));

    expect($html)->toContain('Arrêté la présente facture')
        ->not->toContain('pro forma')
        ->not->toContain('PRO FORMA')
        ->not->toContain('présent devis')
        ->not->toContain('abonnement mensuel sera de')
        ->not->toContain('Conditions de mise en place');
});

it('régénère le PDF quand le statut de la facture change', function () {
    $this->seed(RolePermissionSeeder::class);
    $admin = User::factory()->create(['email' => 'admin-pdf@sis.ci', 'password' => Hash::make('password')]);
    $admin->assignRole('super-admin');

    $facture = makeFacturePdf(StatutFacture::EnAttente);
    Bus::fake([GenererFacturePdfJob::class]);

    $this->actingAs($admin)
        ->patchJson("/api/v1/factures/{$facture->id}/statut", ['statut' => 'annule'])
        ->assertOk();

    Bus::assertDispatchedSync(
        GenererFacturePdfJob::class,
        fn (GenererFacturePdfJob $job) => $job->factureId === $facture->id,
    );
});
