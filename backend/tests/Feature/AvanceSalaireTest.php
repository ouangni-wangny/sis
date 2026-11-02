<?php

use App\Application\Paie\GenererBulletinPaiePdfAction;
use App\Domain\Shared\Enums\StatutPeriodePaie;
use App\Domain\Shared\Enums\TypeAgent;
use App\Models\Agent;
use App\Models\AvanceSalaire;
use App\Models\BulletinPaie;
use App\Models\CompteTresorerie;
use App\Models\Contrat;
use App\Models\Grade;
use App\Models\ModePaiementParam;
use App\Models\MouvementTresorerie;
use App\Models\PeriodePaie;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    Carbon::setTestNow('2026-11-15 10:00:00');
    $this->seed(RolePermissionSeeder::class);

    $this->comptable = User::factory()->create();
    $this->comptable->assignRole('comptable');
    $this->rh = User::factory()->create();
    $this->rh->assignRole('rh');

    ModePaiementParam::query()->firstOrCreate(
        ['code' => 'especes'],
        ['libelle' => 'Espèces', 'actif' => true, 'ordre' => 1],
    );

    $this->compte = CompteTresorerie::query()->create([
        'libelle' => 'Caisse',
        'type' => 'caisse',
        'solde_ouverture' => 1_000_000,
        'actif' => true,
    ]);

    $this->agent = Agent::query()->create([
        'grade_id' => Grade::query()->create(['libelle' => 'Agent', 'type_agent' => TypeAgent::Agent])->id,
        'type' => TypeAgent::Agent,
        'nom' => 'Koffi',
        'prenom' => 'Kouame Richard',
        'matricule' => 'AV-001',
        'statut' => 'disponible',
    ]);

    Contrat::query()->where('agent_id', $this->agent->id)->forceDelete();
    Contrat::query()->create([
        'agent_id' => $this->agent->id,
        'type' => 'cdi',
        'date_debut' => '2026-01-01',
        'salaire_net' => 200000,
        'salaire_brut' => 220000,
        'statut' => 'actif',
    ]);

    $this->donner = fn (array $payload = [], ?User $par = null) => $this
        ->actingAs($par ?? $this->comptable)
        ->postJson('/api/v1/avances-salaire', [
            'agent_id' => $this->agent->id,
            'montant' => 50000,
            'date_avance' => '2026-11-10',
            'mois' => 11,
            'annee' => 2026,
            'mode' => 'especes',
            'compte_tresorerie_id' => $this->compte->id,
            ...$payload,
        ]);

    $this->bulletin = function (float $net = 200000): BulletinPaie {
        $periode = PeriodePaie::query()->create([
            'mois' => 11,
            'annee' => 2026,
            'date_debut' => '2026-11-01',
            'date_fin' => '2026-11-30',
            'statut' => StatutPeriodePaie::Validee,
        ]);

        return BulletinPaie::query()->create([
            'periode_paie_id' => $periode->id,
            'agent_id' => $this->agent->id,
            'contrat_id' => Contrat::query()->where('agent_id', $this->agent->id)->value('id'),
            'salaire_brut' => 220000,
            'salaire_net' => $net,
            'statut' => 'valide',
            'details' => ['agent' => 'Kouame Richard Koffi', 'salaire_percu' => $net, 'salaire_renseigne_le' => now()->toIso8601String()],
        ]);
    };

    $this->regler = fn (BulletinPaie $b) => $this->actingAs($this->comptable)
        ->postJson("/api/v1/bulletins-paie/{$b->id}/marquer-paye", [
            'mode' => 'especes',
            'compte_tresorerie_id' => $this->compte->id,
        ]);

    $this->sorties = fn () => (float) MouvementTresorerie::query()
        ->where('compte_tresorerie_id', $this->compte->id)
        ->where('direction', 'sortie')
        ->sum('montant');
});

afterEach(function () {
    Carbon::setTestNow();
});

it('enregistre l’avance et sort l’argent de la trésorerie le jour de la remise', function () {
    ($this->donner)()
        ->assertCreated()
        ->assertJsonPath('data.statut', 'en_cours')
        ->assertJsonPath('data.situation.salaire_net', 200000)
        ->assertJsonPath('data.situation.avances_total', 50000)
        ->assertJsonPath('data.situation.reste_a_payer', 150000);

    $mouvement = MouvementTresorerie::query()->where('source_type', 'avance_salaire')->sole();

    expect((float) $mouvement->montant)->toBe(50000.0)
        ->and($mouvement->direction->value)->toBe('sortie')
        ->and($mouvement->date_mouvement->toDateString())->toBe('2026-11-10')
        ->and($mouvement->notes)->toContain('AV-001');
});

it('génère une référence si aucune n’est saisie', function () {
    expect(($this->donner)()->json('data.reference'))->toStartWith('AVS-20261115-');
});

it('ne fait sortir que le reste au règlement du bulletin', function () {
    ($this->donner)();
    ($this->donner)(['montant' => 30000]);

    $bulletin = ($this->bulletin)();

    $this->actingAs($this->comptable)->getJson("/api/v1/bulletins-paie/{$bulletin->id}")
        ->assertJsonPath('data.avances_total', 80000)
        ->assertJsonPath('data.reste_a_payer', 120000);

    ($this->regler)($bulletin)
        ->assertOk()
        ->assertJsonPath('data.statut', 'paye')
        ->assertJsonPath('data.reste_a_payer', 0);

    $fresh = $bulletin->fresh();

    expect((float) $fresh->salaire_net)->toBe(200000.0)
        ->and($fresh->details['avances_deduites'])->toEqual(80000)
        ->and($fresh->details['reste_verse'])->toEqual(120000)
        ->and((float) MouvementTresorerie::query()->where('source_type', 'bulletin_paie')->sole()->montant)->toBe(120000.0)
        ->and(($this->sorties)())->toBe(200000.0)
        ->and(AvanceSalaire::query()->where('statut', 'deduite')->where('bulletin_paie_id', $bulletin->id)->count())->toBe(2);
});

it('déduit aussi les avances au règlement groupé', function () {
    ($this->donner)();
    $bulletin = ($this->bulletin)();

    $this->actingAs($this->comptable)->postJson('/api/v1/bulletins-paie/marquer-paye-bulk', [
        'bulletin_ids' => [$bulletin->id],
        'mode' => 'especes',
        'compte_tresorerie_id' => $this->compte->id,
    ])->assertOk();

    expect(($this->sorties)())->toBe(200000.0)
        ->and(AvanceSalaire::query()->sole()->statut->value)->toBe('deduite');
});

it('solde le bulletin sans nouvelle sortie quand les avances couvrent tout le salaire', function () {
    ($this->donner)(['montant' => 200000]);
    $bulletin = ($this->bulletin)();

    ($this->regler)($bulletin)->assertOk()->assertJsonPath('data.statut', 'paye');

    expect(($this->sorties)())->toBe(200000.0)
        ->and(MouvementTresorerie::query()->where('source_type', 'bulletin_paie')->count())->toBe(0);
});

it('refuse des avances supérieures au net du contrat', function () {
    ($this->donner)(['montant' => 150000])->assertCreated();

    ($this->donner)(['montant' => 60000])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['montant']);

    expect(AvanceSalaire::query()->count())->toBe(1)
        ->and(MouvementTresorerie::query()->where('source_type', 'avance_salaire')->count())->toBe(1);
});

it('plafonne sur le net du bulletin quand il existe', function () {
    ($this->bulletin)(100000);

    ($this->donner)(['montant' => 150000])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['montant']);
});

it('accepte l’avance sans plafond quand le salaire n’est pas encore connu', function () {
    Contrat::query()->where('agent_id', $this->agent->id)->update(['salaire_net' => 0]);

    ($this->donner)(['montant' => 500000])
        ->assertCreated()
        ->assertJsonPath('data.situation.salaire_net', null)
        ->assertJsonPath('data.situation.reste_a_payer', null);
});

it('bloque le règlement si le salaire finalement saisi est inférieur aux avances', function () {
    Contrat::query()->where('agent_id', $this->agent->id)->update(['salaire_net' => 0]);
    ($this->donner)(['montant' => 500000])->assertCreated();
    $bulletin = ($this->bulletin)(200000);

    ($this->regler)($bulletin)->assertStatus(422)->assertJsonValidationErrors(['avances']);

    expect($bulletin->fresh()->statut->value)->toBe('valide')
        ->and(AvanceSalaire::query()->sole()->statut->value)->toBe('en_cours');
});

it('refuse une saisie de salaire perçu inférieure aux avances', function () {
    ($this->donner)(['montant' => 100000]);
    $bulletin = ($this->bulletin)();

    $this->actingAs($this->rh)
        ->postJson("/api/v1/bulletins-paie/{$bulletin->id}/salaire-percu", ['salaire_net' => 90000])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['salaire_net']);

    $this->actingAs($this->rh)
        ->postJson('/api/v1/bulletins-paie/salaire-percu-bulk', ['items' => [['id' => $bulletin->id, 'salaire_net' => 90000]]])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['items']);

    $this->actingAs($this->rh)
        ->postJson("/api/v1/bulletins-paie/{$bulletin->id}/salaire-percu", ['salaire_net' => 100000])
        ->assertOk()
        ->assertJsonPath('data.reste_a_payer', 0);
});

it('refuse une avance sur un mois déjà payé', function () {
    ($this->regler)(($this->bulletin)())->assertOk();

    ($this->donner)()->assertStatus(422)->assertJsonValidationErrors(['mois']);
});

it('refuse une avance à un agent sans contrat valide ni bulletin', function () {
    Contrat::query()->where('agent_id', $this->agent->id)->update(['statut' => 'termine', 'date_fin' => '2026-10-31']);

    ($this->donner)()->assertStatus(422)->assertJsonValidationErrors(['agent_id']);
});

it('accepte une avance sur le dernier bulletin d’un contrat terminé', function () {
    ($this->bulletin)();
    Contrat::query()->where('agent_id', $this->agent->id)->update(['statut' => 'termine', 'date_fin' => '2026-10-31']);

    ($this->donner)()->assertCreated();
});

it('valide les données de l’avance', function () {
    ($this->donner)(['montant' => 0])->assertStatus(422)->assertJsonValidationErrors(['montant']);
    ($this->donner)(['date_avance' => '2026-12-01'])->assertStatus(422)->assertJsonValidationErrors(['date_avance']);
    ($this->donner)(['mois' => 13])->assertStatus(422)->assertJsonValidationErrors(['mois']);
    ($this->donner)(['mode' => 'inconnu'])->assertStatus(422)->assertJsonValidationErrors(['mode']);
});

it('annule une avance : l’argent revient et le reste à payer est rétabli', function () {
    $id = ($this->donner)()->json('data.id');

    $this->actingAs($this->comptable)
        ->postJson("/api/v1/avances-salaire/{$id}/annuler", ['motif' => 'Erreur de saisie'])
        ->assertOk()
        ->assertJsonPath('data.statut', 'annulee')
        ->assertJsonPath('data.motif_annulation', 'Erreur de saisie')
        ->assertJsonPath('data.situation.avances_total', 0)
        ->assertJsonPath('data.situation.reste_a_payer', 200000);

    $retour = MouvementTresorerie::query()->where('direction', 'retour')->sole();

    expect((float) $retour->montant)->toBe(50000.0)
        ->and($retour->source_type->value)->toBe('ajustement');

    $bulletin = ($this->bulletin)();
    ($this->regler)($bulletin)->assertOk();

    expect((float) MouvementTresorerie::query()->where('source_type', 'bulletin_paie')->sole()->montant)->toBe(200000.0);
});

it('refuse d’annuler une avance déjà retenue ou déjà annulée', function () {
    $id = ($this->donner)()->json('data.id');
    $this->actingAs($this->comptable)->postJson("/api/v1/avances-salaire/{$id}/annuler")->assertOk();
    $this->actingAs($this->comptable)->postJson("/api/v1/avances-salaire/{$id}/annuler")
        ->assertStatus(422)->assertJsonValidationErrors(['statut']);

    $id2 = ($this->donner)()->json('data.id');
    ($this->regler)(($this->bulletin)())->assertOk();
    $this->actingAs($this->comptable)->postJson("/api/v1/avances-salaire/{$id2}/annuler")
        ->assertStatus(422)->assertJsonValidationErrors(['statut']);

    expect(MouvementTresorerie::query()->where('direction', 'retour')->count())->toBe(1);
});

it('compte l’avance dans la masse salariale payée, sauf si elle est annulée', function () {
    $masse = fn () => $this->actingAs($this->comptable)
        ->getJson('/api/v1/tresorerie/stats?mois=11&annee=2026')
        ->assertOk();

    $id = ($this->donner)()->json('data.id');
    expect($masse()->json('data.paie_mois.total_paye'))->toEqual(50000);

    $this->actingAs($this->comptable)->postJson("/api/v1/avances-salaire/{$id}/annuler")->assertOk();
    expect($masse()->json('data.paie_mois.total_paye'))->toEqual(0);
});

it('garde le bon solde de trésorerie après avance et règlement', function () {
    ($this->donner)();
    ($this->regler)(($this->bulletin)())->assertOk();

    expect((float) $this->compte->fresh()->soldeCourant())->toBe(800000.0);
});

it('liste, filtre et totalise les avances', function () {
    ($this->donner)();
    ($this->donner)(['montant' => 20000, 'mois' => 12]);
    $annulee = ($this->donner)(['montant' => 10000])->json('data.id');
    $this->actingAs($this->comptable)->postJson("/api/v1/avances-salaire/{$annulee}/annuler")->assertOk();

    $this->actingAs($this->comptable)->getJson('/api/v1/avances-salaire?mois=11&annee=2026')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('total_donne', 50000);

    $this->actingAs($this->comptable)->getJson('/api/v1/avances-salaire?statut=annulee')
        ->assertJsonCount(1, 'data');

    $this->actingAs($this->comptable)->getJson('/api/v1/avances-salaire?q=Richard+Koffi')
        ->assertJsonCount(3, 'data');

    $this->actingAs($this->comptable)->getJson("/api/v1/avances-salaire?agent_id={$this->agent->id}")
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('total_donne', 70000);
});

it('donne la situation du mois d’un agent', function () {
    ($this->donner)();

    $this->actingAs($this->rh)
        ->getJson("/api/v1/avances-salaire/situation?agent_id={$this->agent->id}&mois=11&annee=2026")
        ->assertOk()
        ->assertJsonPath('data.source', 'contrat')
        ->assertJsonPath('data.reste_a_payer', 150000);

    ($this->bulletin)(180000);

    $this->actingAs($this->rh)
        ->getJson("/api/v1/avances-salaire/situation?agent_id={$this->agent->id}&mois=11&annee=2026")
        ->assertJsonPath('data.source', 'bulletin')
        ->assertJsonPath('data.reste_a_payer', 130000);
});

it('expose avances et reste dans la liste des bulletins de la période, sans N+1', function () {
    ($this->donner)();
    $bulletin = ($this->bulletin)();

    DB::enableQueryLog();
    $this->actingAs($this->comptable)
        ->getJson("/api/v1/periodes-paie/{$bulletin->periode_paie_id}/bulletins")
        ->assertOk()
        ->assertJsonPath('data.0.avances_total', 50000)
        ->assertJsonPath('data.0.reste_a_payer', 150000);

    $requetes = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'avances_salaire'));
    expect($requetes)->toHaveCount(1);
});

it('expose les avances dans les bulletins de la fiche agent', function () {
    ($this->donner)();
    ($this->bulletin)();

    $this->actingAs($this->comptable)
        ->getJson("/api/v1/agents/{$this->agent->id}/bulletins")
        ->assertOk()
        ->assertJsonPath('data.0.reste_a_payer', 150000);
});

it('réserve la remise et l’annulation au comptable, la lecture à ceux qui voient les salaires', function () {
    ($this->donner)([], $this->rh)->assertForbidden();

    $id = ($this->donner)()->assertCreated()->json('data.id');

    $this->actingAs($this->rh)->postJson("/api/v1/avances-salaire/{$id}/annuler")->assertForbidden();
    $this->actingAs($this->rh)->getJson('/api/v1/avances-salaire')->assertOk();

    $sansDroit = User::factory()->create();
    $this->actingAs($sansDroit)->getJson('/api/v1/avances-salaire')->assertForbidden();

    $lecteurPaie = User::factory()->create();
    $lecteurPaie->givePermissionTo('paie.view');
    $this->actingAs($lecteurPaie)->getJson('/api/v1/avances-salaire')->assertForbidden();
    $this->actingAs($lecteurPaie)
        ->getJson("/api/v1/avances-salaire/situation?agent_id={$this->agent->id}&mois=11&annee=2026")
        ->assertForbidden();
});

it('cache avances et reste du bulletin à qui ne voit pas les salaires', function () {
    ($this->donner)();
    $bulletin = ($this->bulletin)();
    $lecteurPaie = User::factory()->create();
    $lecteurPaie->givePermissionTo('paie.view');

    $this->actingAs($lecteurPaie)->getJson("/api/v1/bulletins-paie/{$bulletin->id}")
        ->assertOk()
        ->assertJsonPath('data.avances_total', null)
        ->assertJsonPath('data.reste_a_payer', null);
});

it('fait figurer avances et reste sur le PDF du bulletin', function () {
    ($this->donner)();
    $bulletin = ($this->bulletin)();
    $bulletin->load(['agent.grade', 'contrat', 'periodePaie', 'compteTresorerie']);

    $action = app(GenererBulletinPaiePdfAction::class);
    $action->execute($bulletin);

    expect($bulletin->fresh()->getFirstMedia('pdf'))->not->toBeNull();

    $html = view('pdf.bulletin-paie', ['bulletin' => $bulletin, 'generatedAt' => '15/11/2026 10:00'])->render();
    expect($html)->toContain('Avances sur salaire déjà versées')
        ->toContain('Reste à payer')
        ->toContain('150 000');
});
