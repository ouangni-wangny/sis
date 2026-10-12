<?php

/*
 * Retour RH (prod) — les agents en fin de CDD / stage (31 août, courant
 * septembre) n’apparaissaient plus dans la liste paie : la génération ne
 * retenait que les contrats encore actifs le jour de la génération.
 */

use App\Domain\Shared\Enums\StatutPeriodePaie;
use App\Domain\Shared\Enums\TypeAgent;
use App\Jobs\CloturerContratsExpiresJob;
use App\Models\Agent;
use App\Models\BulletinPaie;
use App\Models\Contrat;
use App\Models\Grade;
use App\Models\PeriodePaie;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->rh = User::factory()->create(['email' => 'rh-fin-contrat@sis.ci']);
    $this->rh->assignRole('rh');

    $grade = Grade::query()->create(['libelle' => 'Agent', 'type_agent' => TypeAgent::Agent]);
    $this->agent = function (string $matricule, string $type, string $debut, ?string $fin, string $statut = 'actif') use ($grade) {
        $agent = Agent::query()->create([
            'grade_id' => $grade->id,
            'type' => TypeAgent::Agent,
            'nom' => 'Paie',
            'prenom' => $matricule,
            'matricule' => $matricule,
            'statut' => 'en_activite',
        ]);
        // TestCase ajoute un CDI par défaut : on ne garde que le contrat du scénario.
        Contrat::query()->where('agent_id', $agent->id)->forceDelete();
        Contrat::query()->create([
            'agent_id' => $agent->id,
            'type' => $type,
            'date_debut' => $debut,
            'date_fin' => $fin,
            'statut' => $statut,
            'salaire_brut' => 120000,
            'salaire_net' => 100000,
        ]);

        return $agent;
    };

    $this->septembre = PeriodePaie::query()->create([
        'mois' => 9,
        'annee' => 2026,
        'date_debut' => '2026-09-01',
        'date_fin' => '2026-09-30',
        'statut' => StatutPeriodePaie::Brouillon,
    ]);

    $this->generer = fn () => $this->actingAs($this->rh)
        ->postJson("/api/v1/periodes-paie/{$this->septembre->id}/generer-bulletins")
        ->assertOk();

    $this->bulletin = fn (Agent $a) => BulletinPaie::query()
        ->where('periode_paie_id', $this->septembre->id)
        ->where('agent_id', $a->id)
        ->first();
});

afterEach(fn () => Carbon::setTestNow());

it('garde les CDD et stages terminés en cours de mois, même clôturés', function () {
    $cddFin15 = ($this->agent)('CDD-15', 'cdd', '2026-03-01', '2026-09-15');
    $stageFin30 = ($this->agent)('STG-30', 'stage', '2026-07-01', '2026-09-30');
    $cdi = ($this->agent)('CDI-01', 'cdi', '2025-01-01', null);

    // Génération faite en octobre : les contrats échus ont été clôturés la nuit.
    Carbon::setTestNow('2026-10-12 09:00:00');
    (new CloturerContratsExpiresJob)->handle(app(\App\Application\Contrat\HandleContratClotureAction::class));
    expect(Contrat::query()->where('statut', 'termine')->count())->toBe(2);

    ($this->generer)();

    expect(($this->bulletin)($cddFin15))->not->toBeNull()
        ->and(($this->bulletin)($cddFin15)->details['motif'])->toBe('fin_contrat')
        ->and(($this->bulletin)($cddFin15)->details['contrat_fin'])->toBe('2026-09-15')
        ->and(($this->bulletin)($stageFin30))->not->toBeNull()
        ->and(($this->bulletin)($cdi)->details['motif'])->toBe('periode');
});

it('ajoute le solde de tout compte d’un contrat terminé le 31 août jamais payé', function () {
    $finAout = ($this->agent)('CDD-AOUT', 'cdd', '2026-02-01', '2026-08-31', 'termine');

    ($this->generer)();

    $b = ($this->bulletin)($finAout);
    expect($b)->not->toBeNull()
        ->and($b->details['motif'])->toBe('solde_tout_compte')
        ->and($b->details['contrat_fin'])->toBe('2026-08-31');
});

it('n’ajoute pas un contrat terminé en août déjà payé sur août', function () {
    $finAout = ($this->agent)('CDD-PAYE', 'cdd', '2026-02-01', '2026-08-31', 'termine');
    $aout = PeriodePaie::query()->create([
        'mois' => 8, 'annee' => 2026, 'date_debut' => '2026-08-01', 'date_fin' => '2026-08-31',
        'statut' => StatutPeriodePaie::Cloturee,
    ]);
    BulletinPaie::query()->create([
        'periode_paie_id' => $aout->id,
        'agent_id' => $finAout->id,
        'contrat_id' => $finAout->contrats()->value('id'),
        'salaire_brut' => 120000, 'retenue_cnps' => 0, 'montant_igr' => 0, 'salaire_net' => 100000,
        'statut' => 'paye',
    ]);

    ($this->generer)();

    expect(($this->bulletin)($finAout))->toBeNull();
});

it('ignore les contrats terminés depuis plus d’un mois et les suspendus', function () {
    $ancien = ($this->agent)('CDD-JUIL', 'cdd', '2026-01-01', '2026-07-31', 'termine');
    $suspendu = ($this->agent)('CDI-SUSP', 'cdi', '2025-01-01', null, 'suspendu');

    ($this->generer)();

    expect(($this->bulletin)($ancien))->toBeNull()
        ->and(($this->bulletin)($suspendu))->toBeNull();
});

it('relancer la génération ne touche pas aux salaires déjà saisis ni aux bulletins payés', function () {
    $saisi = ($this->agent)('CDI-SAISI', 'cdi', '2025-01-01', null);
    $paye = ($this->agent)('CDI-PAYE', 'cdi', '2025-01-01', null);
    ($this->generer)();

    // La RH saisit un salaire, le comptable règle l’autre bulletin.
    $this->actingAs($this->rh)
        ->postJson('/api/v1/bulletins-paie/'.($this->bulletin)($saisi)->id.'/salaire-percu', ['salaire_net' => 87500])
        ->assertOk();
    ($this->bulletin)($paye)->update(['statut' => 'paye', 'salaire_net' => 95000]);

    // Un nouvel agent arrive, la RH relance la génération.
    $nouveau = ($this->agent)('CDD-NOUV', 'cdd', '2026-09-10', '2027-03-09');
    ($this->generer)();

    expect((float) ($this->bulletin)($saisi)->details['salaire_percu'])->toBe(87500.0)
        ->and((float) ($this->bulletin)($saisi)->salaire_net)->toBe(87500.0)
        ->and(($this->bulletin)($paye)->statut->value)->toBe('paye')
        ->and((float) ($this->bulletin)($paye)->salaire_net)->toBe(95000.0)
        ->and(($this->bulletin)($nouveau))->not->toBeNull();
});

it('explique la situation d’un agent avec la commande de diagnostic', function () {
    ($this->agent)('CDD-DIAG', 'cdd', '2026-03-01', '2026-09-15', 'termine');

    $this->artisan('paie:diagnostic', ['mois' => 9, 'annee' => 2026, 'noms' => ['cdd-diag paie', 'Inconnu Total']])
        ->expectsOutputToContain('[couvre la période]')
        ->expectsOutputToContain('Pas de bulletin dans cette période')
        ->expectsOutputToContain('Agent introuvable')
        ->assertSuccessful();

    // Lecture seule : aucun bulletin créé par le diagnostic.
    expect(BulletinPaie::query()->count())->toBe(0);
});

it('crée en « validé » les bulletins ajoutés à une période déjà validée', function () {
    $present = ($this->agent)('CDI-AVANT', 'cdi', '2025-01-01', null);
    ($this->generer)();
    $this->actingAs($this->rh)->postJson("/api/v1/periodes-paie/{$this->septembre->id}/valider")->assertOk();

    $tardif = ($this->agent)('CDD-TARD', 'cdd', '2025-10-01', '2027-03-30');
    ($this->generer)();

    expect(($this->bulletin)($present)->statut->value)->toBe('valide')
        ->and(($this->bulletin)($tardif)->statut->value)->toBe('valide');
});

it('trace dans le journal d’audit la saisie du salaire par la RH', function () {
    $agent = ($this->agent)('CDI-AUDIT', 'cdi', '2025-01-01', null);
    ($this->generer)();
    $bulletin = ($this->bulletin)($agent);

    $this->actingAs($this->rh)
        ->postJson("/api/v1/bulletins-paie/{$bulletin->id}/salaire-percu", ['salaire_net' => 91000])
        ->assertOk();

    $entree = \App\Models\JournalAudit::query()
        ->where('auditable_id', $bulletin->id)
        ->where('action', 'updated')
        ->latest('created_at')
        ->first();

    expect($entree)->not->toBeNull()
        ->and($entree->user_id)->toBe($this->rh->id)
        ->and((float) $entree->nouveau['salaire_net'])->toBe(91000.0)
        ->and($entree->resume)->toContain('Bulletin 09/2026');
});
