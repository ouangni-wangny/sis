<?php

use App\Application\Commercial\GenererFacturesRecurrentesAction;
use App\Domain\Shared\Enums\PeriodiciteFacturation;
use App\Models\Abonnement;
use App\Models\Client;
use App\Models\Facture;
use App\Models\LigneAbonnement;
use App\Models\Offre;
use App\Models\Site;
use App\Models\User;
use App\Models\Zone;
use App\Support\PdfBrand;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('super-admin');

    $this->client = Client::query()->create(['type' => 'entreprise', 'raison_sociale' => 'Client A', 'statut' => 'actif']);
    $this->autreClient = Client::query()->create(['type' => 'entreprise', 'raison_sociale' => 'Client B', 'statut' => 'actif']);
    $zone = Zone::query()->create(['nom' => 'Zone']);
    $this->site1 = Site::query()->create(['client_id' => $this->client->id, 'zone_id' => $zone->id, 'nom' => 'Site 1']);
    $this->site2 = Site::query()->create(['client_id' => $this->client->id, 'zone_id' => $zone->id, 'nom' => 'Site 2']);
    $this->siteAutre = Site::query()->create(['client_id' => $this->autreClient->id, 'zone_id' => $zone->id, 'nom' => 'Site B']);

    $this->facture = fn (string $numero, ?Site $site, string $emission, float $ttc, ?Client $client = null) => Facture::query()->create([
        'client_id' => ($client ?? $this->client)->id,
        'site_id' => $site?->id,
        'numero' => $numero,
        'date_emission' => $emission,
        'montant_ht' => $ttc,
        'montant_tva' => 0,
        'montant_ttc' => $ttc,
        'statut' => 'valide',
    ]);

    $this->proforma = fn (array $payload = []) => $this->actingAs($this->admin)->postJson('/api/v1/factures/proforma', [
        'client_id' => $this->client->id,
        'site_id' => $this->site1->id,
        'lignes' => [['description' => 'Gardiennage', 'quantite' => 1, 'prix_unitaire' => 1000]],
        ...$payload,
    ]);

    $this->numeros = fn (string $query) => collect(
        $this->actingAs($this->admin)->getJson("/api/v1/factures?{$query}")->assertOk()->json('data')
    )->pluck('numero')->all();
});

it('garde une facture par site, sans cumuler les montants du client', function () {
    ($this->facture)('F-1', $this->site1, '2026-10-31', 1000);
    ($this->facture)('F-2', $this->site2, '2026-10-31', 2000);

    $data = $this->actingAs($this->admin)
        ->getJson("/api/v1/factures?client_id={$this->client->id}")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->json('data');

    expect(collect($data)->pluck('montant_ttc')->map(fn ($m) => (float) $m)->all())->toBe([1000.0, 2000.0])
        ->and(collect($data)->pluck('site.nom')->all())->toBe(['Site 1', 'Site 2']);
});

it('range les factures d’un client par mois puis par site', function () {
    ($this->facture)('F-OCT-2', $this->site2, '2026-10-31', 2000);
    ($this->facture)('F-SEP-1', $this->site1, '2026-09-30', 1000);
    ($this->facture)('F-OCT-1', $this->site1, '2026-10-31', 1000);
    ($this->facture)('F-SEP-2', $this->site2, '2026-09-30', 2000);

    expect(($this->numeros)("client_id={$this->client->id}"))
        ->toBe(['F-OCT-1', 'F-OCT-2', 'F-SEP-1', 'F-SEP-2']);
});

it('filtre par site et par mois', function () {
    ($this->facture)('F-OCT-1', $this->site1, '2026-10-31', 1000);
    ($this->facture)('F-OCT-2', $this->site2, '2026-10-31', 2000);
    ($this->facture)('F-SEP-1', $this->site1, '2026-09-30', 1000);

    expect(($this->numeros)("client_id={$this->client->id}&site_id={$this->site1->id}"))->toBe(['F-OCT-1', 'F-SEP-1'])
        ->and(($this->numeros)("client_id={$this->client->id}&mois=10&annee=2026"))->toBe(['F-OCT-1', 'F-OCT-2'])
        ->and(($this->numeros)("site_id={$this->site2->id}&mois=9&annee=2026"))->toBe([]);
});

it('isole les anciennes factures sans site', function () {
    ($this->facture)('F-CUMUL', null, '2026-10-19', 24413807);
    ($this->facture)('F-1', $this->site1, '2026-10-31', 1000);

    expect(($this->numeros)("client_id={$this->client->id}&site_id=sans_site"))->toBe(['F-CUMUL']);
});

it('exige le site sur une proforma quand le client a des sites', function () {
    ($this->proforma)(['site_id' => null])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['site_id']);

    ($this->proforma)()
        ->assertOk()
        ->assertJsonPath('data.site_id', $this->site1->id);
});

it('refuse un site d’un autre client', function () {
    ($this->proforma)(['site_id' => $this->siteAutre->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['site_id']);
});

it('n’exige pas de site pour un client sans site ni un destinataire libre', function () {
    $sansSite = Client::query()->create(['type' => 'entreprise', 'raison_sociale' => 'Sans site', 'statut' => 'actif']);

    ($this->proforma)(['client_id' => $sansSite->id, 'site_id' => null])->assertOk();
    ($this->proforma)(['client_id' => null, 'client_nom' => 'Prospect', 'site_id' => null])->assertOk();
});

it('exige le site à la création d’un abonnement quand le client a des sites', function () {
    $offre = Offre::query()->create(['libelle' => 'Gardiennage', 'prix_mensuel' => 1000, 'actif' => true]);

    $this->actingAs($this->admin)->postJson('/api/v1/abonnements', [
        'client_id' => $this->client->id,
        'offre_id' => $offre->id,
        'periodicite' => 'mensuel',
        'date_debut' => '2026-11-01',
    ])->assertStatus(422)->assertJsonValidationErrors(['site_id']);
});

it('génère une facture récurrente distincte pour chaque site', function () {
    Carbon::setTestNow('2026-11-05 10:00:00');
    $offre = Offre::query()->create(['libelle' => 'Gardiennage', 'prix_mensuel' => 1000, 'actif' => true]);

    foreach ([[$this->site1, 1000], [$this->site2, 2000]] as [$site, $prix]) {
        $abonnement = Abonnement::query()->create([
            'client_id' => $this->client->id,
            'site_id' => $site->id,
            'offre_id' => $offre->id,
            'designation' => 'Gardiennage',
            'periodicite' => PeriodiciteFacturation::Mensuel,
            'date_debut' => '2026-11-01',
            'prochaine_facture_le' => '2026-11-01',
            'statut' => 'actif',
        ]);
        LigneAbonnement::query()->create([
            'abonnement_id' => $abonnement->id,
            'description' => 'Gardiennage',
            'quantite' => 1,
            'prix_unitaire' => $prix,
            'montant' => $prix,
            'ordre' => 1,
        ]);
    }

    app(GenererFacturesRecurrentesAction::class)->execute(now());

    $factures = Facture::query()->where('client_id', $this->client->id)->get();

    expect($factures)->toHaveCount(2)
        ->and($factures->pluck('site_id')->sort()->values()->all())
        ->toBe(collect([$this->site1->id, $this->site2->id])->sort()->values()->all())
        ->and($factures->firstWhere('site_id', $this->site1->id)->montant_ht)->toEqual(1000)
        ->and($factures->firstWhere('site_id', $this->site2->id)->montant_ht)->toEqual(2000);

    Carbon::setTestNow();
});

it('imprime le site sur le PDF de la facture', function () {
    $facture = ($this->facture)('F-1', $this->site1, '2026-10-31', 1000);

    $html = view('pdf.facture', ['facture' => $facture->load(['client', 'lignes', 'site', 'abonnement']), 'brand' => PdfBrand::data()])->render();

    expect($html)->toContain('Site 1');
});
