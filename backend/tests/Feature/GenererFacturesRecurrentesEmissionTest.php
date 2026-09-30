<?php

use App\Application\Commercial\GenererFacturesRecurrentesAction;
use App\Domain\Shared\Enums\PeriodiciteFacturation;
use App\Domain\Shared\Enums\StatutAbonnement;
use App\Domain\Shared\Enums\StatutClient;
use App\Domain\Shared\Enums\TypeClient;
use App\Jobs\GenererFacturePdfJob;
use App\Models\Abonnement;
use App\Models\Client;
use App\Models\LigneAbonnement;
use App\Models\Offre;
use Carbon\Carbon;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake([GenererFacturePdfJob::class]);
});

it('émet la facture récurrente au dernier jour de la période, pas à la date de création', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-03 01:00:00', 'Africa/Abidjan'));

    $client = Client::query()->create([
        'type' => TypeClient::Entreprise,
        'raison_sociale' => 'Client Test Facture',
        'telephone' => '0700000000',
        'email' => 'client-facture@test.ci',
        'adresse' => 'Abidjan',
        'statut' => StatutClient::Actif,
    ]);

    $offre = Offre::query()->create([
        'libelle' => 'Gardiennage',
        'prix_mensuel' => 100000,
        'actif' => true,
    ]);

    $abonnement = Abonnement::query()->create([
        'client_id' => $client->id,
        'offre_id' => $offre->id,
        'designation' => 'Gardiennage mensuel',
        'periodicite' => PeriodiciteFacturation::Mensuel,
        'date_debut' => '2026-09-01',
        'statut' => StatutAbonnement::Actif,
        'prochaine_facture_le' => '2026-09-01',
    ]);

    LigneAbonnement::query()->create([
        'abonnement_id' => $abonnement->id,
        'offre_id' => $offre->id,
        'description' => 'Gardiennage',
        'quantite' => 1,
        'prix_unitaire' => 100000,
        'montant' => 100000,
        'ordre' => 1,
    ]);

    $stats = app(GenererFacturesRecurrentesAction::class)->execute();

    expect($stats['generated'])->toBeGreaterThanOrEqual(1);

    $facture = $abonnement->factures()->orderBy('periode_debut')->first();

    expect($facture)->not->toBeNull()
        ->and($facture->periode_debut->toDateString())->toBe('2026-09-01')
        ->and($facture->periode_fin->toDateString())->toBe('2026-09-30')
        ->and($facture->date_emission->toDateString())->toBe('2026-09-30')
        ->and($facture->date_emission->toDateString())->not->toBe('2026-10-03');

    Carbon::setTestNow();
});
