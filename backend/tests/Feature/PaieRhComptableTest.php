<?php

use App\Domain\Shared\Enums\StatutBulletinPaie;
use App\Domain\Shared\Enums\StatutPeriodePaie;
use App\Domain\Shared\Enums\TypeAgent;
use App\Models\Agent;
use App\Models\BulletinPaie;
use App\Models\CompteTresorerie;
use App\Models\Contrat;
use App\Models\Grade;
use App\Models\ModePaiementParam;
use App\Models\PeriodePaie;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->rh = User::factory()->create([
        'email' => 'rh-paie@sis.ci',
        'password' => Hash::make('password'),
    ]);
    $this->rh->assignRole('rh');

    $this->comptable = User::factory()->create([
        'email' => 'comptable-paie@sis.ci',
        'password' => Hash::make('password'),
    ]);
    $this->comptable->assignRole('comptable');

    ModePaiementParam::query()->firstOrCreate(
        ['code' => 'virement'],
        [
            'libelle' => 'Virement',
            'actif' => true,
            'ordre' => 1,
        ],
    );

    $this->compte = CompteTresorerie::query()->create([
        'libelle' => 'Banque paie',
        'type' => 'banque',
        'solde_ouverture' => 5_000_000,
        'actif' => true,
    ]);

    $grade = Grade::query()->create([
        'libelle' => 'Agent',
        'type_agent' => TypeAgent::Agent,
    ]);

    $this->agent = Agent::query()->create([
        'grade_id' => $grade->id,
        'type' => TypeAgent::Agent,
        'nom' => 'Kone',
        'prenom' => 'Awa',
        'matricule' => 'PAIE-001',
        'statut' => 'disponible',
    ]);

    $this->contrat = Contrat::query()->create([
        'agent_id' => $this->agent->id,
        'type' => 'cdi',
        'date_debut' => '2026-01-01',
        'statut' => 'actif',
        'salaire_brut' => 150000,
        'salaire_net' => 120000,
    ]);

    $this->periode = PeriodePaie::query()->create([
        'mois' => 9,
        'annee' => 2026,
        'date_debut' => '2026-09-01',
        'date_fin' => '2026-09-30',
        'statut' => StatutPeriodePaie::Validee,
    ]);

    $this->bulletin = BulletinPaie::query()->create([
        'periode_paie_id' => $this->periode->id,
        'agent_id' => $this->agent->id,
        'contrat_id' => $this->contrat->id,
        'salaire_brut' => 150000,
        'retenue_cnps' => 10000,
        'montant_igr' => 20000,
        'salaire_net' => 120000,
        'statut' => StatutBulletinPaie::Valide,
        'details' => [
            'matricule' => $this->agent->matricule,
        ],
    ]);
});

it('permet à la RH de renseigner le salaire perçu', function () {
    $response = $this->actingAs($this->rh)->postJson(
        "/api/v1/bulletins-paie/{$this->bulletin->id}/salaire-percu",
        ['salaire_net' => 110000],
    );

    $response->assertOk()
        ->assertJsonPath('data.salaire_renseigne', true)
        ->assertJsonPath('data.statut', 'valide');

    expect((float) $response->json('data.salaire_net'))->toBe(110000.0)
        ->and((float) $this->bulletin->fresh()->details['salaire_percu'])->toBe(110000.0);
});

it('refuse au comptable de renseigner le salaire perçu', function () {
    $this->actingAs($this->comptable)->postJson(
        "/api/v1/bulletins-paie/{$this->bulletin->id}/salaire-percu",
        ['salaire_net' => 110000],
    )->assertForbidden();
});

it('masque les salaires au comptable et expose salaire_renseigne', function () {
    $this->bulletin->update([
        'details' => array_merge($this->bulletin->details ?? [], [
            'salaire_percu' => 110000,
            'salaire_renseigne_le' => now()->toIso8601String(),
        ]),
        'salaire_net' => 110000,
    ]);

    $response = $this->actingAs($this->comptable)->getJson(
        "/api/v1/bulletins-paie/{$this->bulletin->id}",
    );

    $response->assertOk()
        ->assertJsonPath('data.salaire_net', null)
        ->assertJsonPath('data.salaire_brut', null)
        ->assertJsonPath('data.details', null)
        ->assertJsonPath('data.salaire_renseigne', true);
});

it('refuse à la RH de marquer payé', function () {
    $this->bulletin->update([
        'details' => [
            'salaire_percu' => 110000,
            'salaire_renseigne_le' => now()->toIso8601String(),
        ],
        'salaire_net' => 110000,
    ]);

    $this->actingAs($this->rh)->postJson(
        "/api/v1/bulletins-paie/{$this->bulletin->id}/marquer-paye",
        [
            'mode' => 'virement',
            'compte_tresorerie_id' => $this->compte->id,
        ],
    )->assertForbidden();
});

it('permet au comptable de marquer payé sans envoyer le montant', function () {
    $this->bulletin->update([
        'details' => [
            'salaire_percu' => 110000,
            'salaire_renseigne_le' => now()->toIso8601String(),
        ],
        'salaire_net' => 110000,
    ]);

    $response = $this->actingAs($this->comptable)->postJson(
        "/api/v1/bulletins-paie/{$this->bulletin->id}/marquer-paye",
        [
            'mode' => 'virement',
            'compte_tresorerie_id' => $this->compte->id,
        ],
    );

    $response->assertOk()
        ->assertJsonPath('data.statut', 'paye')
        ->assertJsonPath('data.salaire_net', null);

    expect($this->bulletin->fresh())
        ->statut->value->toBe('paye')
        ->salaire_net->toBe('110000.00')
        ->mode_paiement->toBe('virement');
});

it('refuse le règlement si la RH n’a pas saisi le salaire perçu', function () {
    $this->actingAs($this->comptable)->postJson(
        "/api/v1/bulletins-paie/{$this->bulletin->id}/marquer-paye",
        [
            'mode' => 'virement',
            'compte_tresorerie_id' => $this->compte->id,
        ],
    )->assertUnprocessable()
        ->assertJsonValidationErrors('salaire_net');
});

it('renseigne les salaires perçus en masse en une seule requête', function () {
    $agent2 = Agent::query()->create([
        'grade_id' => $this->agent->grade_id,
        'type' => TypeAgent::Agent,
        'nom' => 'Bamba',
        'prenom' => 'Ibrahim',
        'matricule' => 'PAIE-002',
        'statut' => 'disponible',
    ]);
    $contrat2 = Contrat::query()->create([
        'agent_id' => $agent2->id,
        'type' => 'cdi',
        'date_debut' => '2026-01-01',
        'statut' => 'actif',
        'salaire_brut' => 140000,
        'salaire_net' => 110000,
    ]);
    $b2 = BulletinPaie::query()->create([
        'periode_paie_id' => $this->periode->id,
        'agent_id' => $agent2->id,
        'contrat_id' => $contrat2->id,
        'salaire_brut' => 140000,
        'retenue_cnps' => 10000,
        'montant_igr' => 20000,
        'salaire_net' => 110000,
        'statut' => StatutBulletinPaie::Valide,
        'details' => [],
    ]);

    $response = $this->actingAs($this->rh)->postJson(
        '/api/v1/bulletins-paie/salaire-percu-bulk',
        [
            'items' => [
                ['id' => $this->bulletin->id, 'salaire_net' => 111000],
                ['id' => $b2->id, 'salaire_net' => 99000],
            ],
        ],
    );

    $response->assertOk()->assertJsonPath('data.updated', 2);
    expect((float) $this->bulletin->fresh()->details['salaire_percu'])->toBe(111000.0)
        ->and((float) $b2->fresh()->details['salaire_percu'])->toBe(99000.0);
});

it('règle plusieurs bulletins en masse', function () {
    $this->bulletin->update([
        'details' => [
            'salaire_percu' => 110000,
            'salaire_renseigne_le' => now()->toIso8601String(),
        ],
        'salaire_net' => 110000,
    ]);

    $agent2 = Agent::query()->create([
        'grade_id' => $this->agent->grade_id,
        'type' => TypeAgent::Agent,
        'nom' => 'Coulibaly',
        'prenom' => 'Fatou',
        'matricule' => 'PAIE-003',
        'statut' => 'disponible',
    ]);
    $contrat2 = Contrat::query()->create([
        'agent_id' => $agent2->id,
        'type' => 'cdi',
        'date_debut' => '2026-01-01',
        'statut' => 'actif',
        'salaire_brut' => 130000,
        'salaire_net' => 100000,
    ]);
    $b2 = BulletinPaie::query()->create([
        'periode_paie_id' => $this->periode->id,
        'agent_id' => $agent2->id,
        'contrat_id' => $contrat2->id,
        'salaire_brut' => 130000,
        'retenue_cnps' => 10000,
        'montant_igr' => 20000,
        'salaire_net' => 100000,
        'statut' => StatutBulletinPaie::Valide,
        'details' => [
            'salaire_percu' => 100000,
            'salaire_renseigne_le' => now()->toIso8601String(),
        ],
    ]);

    $response = $this->actingAs($this->comptable)->postJson(
        '/api/v1/bulletins-paie/marquer-paye-bulk',
        [
            'bulletin_ids' => [$this->bulletin->id, $b2->id],
            'mode' => 'virement',
            'compte_tresorerie_id' => $this->compte->id,
        ],
    );

    $response->assertOk()->assertJsonPath('data.updated', 2);
    expect($this->bulletin->fresh()->statut->value)->toBe('paye')
        ->and($b2->fresh()->statut->value)->toBe('paye');
});
