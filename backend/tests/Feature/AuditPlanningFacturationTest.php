<?php

/*
 * Audit des retours client — écarts de logique corrigés :
 * - une absence ne met « à recouvrir » que les jours absents (pas toute l’affectation) ;
 * - un contrôle n’est rattaché qu’à un seul créneau ; « à recouvrir » = absent ;
 * - modifier une vacation vers une période la découpe par jour ;
 * - une validation de proforma refusée laisse la facture en attente.
 */

use App\Application\Reporting\PointageVacationsAction;
use App\Domain\Shared\Enums\TypeAgent;
use App\Models\Agent;
use App\Models\Client;
use App\Models\Controle;
use App\Models\Facture;
use App\Models\Grade;
use App\Models\Poste;
use App\Models\Ronde;
use App\Models\Site;
use App\Models\User;
use App\Models\Vacation;
use App\Models\Zone;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->admin = User::factory()->create(['email' => 'audit-planning@sis.ci']);
    $this->admin->assignRole('super-admin');

    $grade = Grade::query()->create(['libelle' => 'Agent', 'type_agent' => TypeAgent::Agent]);
    $mk = fn (string $m) => Agent::query()->create([
        'grade_id' => $grade->id,
        'type' => TypeAgent::Agent,
        'nom' => 'Audit',
        'prenom' => $m,
        'matricule' => $m,
        'statut' => 'disponible',
    ]);
    $this->titulaire = $mk('AU-1');
    $this->remplacant = $mk('AU-2');

    $client = Client::query()->create(['type' => 'entreprise', 'raison_sociale' => 'Client audit', 'statut' => 'actif']);
    $zone = Zone::query()->create(['nom' => 'Zone audit']);
    $this->site = Site::query()->create(['client_id' => $client->id, 'zone_id' => $zone->id, 'nom' => 'Site audit']);
    $this->poste = Poste::query()->create([
        'site_id' => $this->site->id,
        'nom' => 'Poste audit',
        'agents_requis' => 1,
        'heure_debut' => '07:00',
        'heure_fin' => '19:00',
    ]);
});

function auditVacation(array $attrs): Vacation
{
    return Vacation::query()->create([
        'statut' => 'planifiee',
        'heure_debut' => '07:00',
        'heure_fin' => '19:00',
        ...$attrs,
    ]);
}

/** @return list<string> "AGENT debut→fin statut" */
function auditPlanning(): array
{
    return Vacation::query()->with('agent')->orderBy('date_debut')->orderBy('created_at')->get()
        ->map(fn (Vacation $v) => sprintf(
            '%s %s→%s %s',
            $v->agent->matricule,
            $v->date_debut->toDateString(),
            $v->date_fin?->toDateString() ?? 'sans fin',
            $v->statut->value,
        ))->all();
}

function approuverAbsence($test, string $agentId, string $debut, string $fin): void
{
    $test->actingAs($test->admin)->postJson('/api/v1/absences', [
        'agent_id' => $agentId,
        'type' => 'maladie',
        'date_debut' => $debut,
        'date_fin' => $fin,
        'statut' => 'approuvee',
    ])->assertCreated();
}

it('ne met à recouvrir que les jours d’absence d’une affectation sans fin', function () {
    auditVacation([
        'agent_id' => $this->titulaire->id,
        'site_id' => $this->site->id,
        'poste_id' => $this->poste->id,
        'date_debut' => '2026-11-01',
        'date_fin' => null,
    ]);

    approuverAbsence($this, $this->titulaire->id, '2026-11-10', '2026-11-11');

    expect(auditPlanning())->toBe([
        'AU-1 2026-11-01→2026-11-09 planifiee',
        'AU-1 2026-11-10→2026-11-11 a_recouvrir',
        'AU-1 2026-11-12→sans fin planifiee',
    ]);

    $trou = Vacation::query()->where('statut', 'a_recouvrir')->firstOrFail();
    $this->actingAs($this->admin)
        ->postJson("/api/v1/vacations/{$trou->id}/recouvrir", ['agent_id' => $this->remplacant->id])
        ->assertCreated();

    // Le remplaçant ne reprend que les deux jours, le titulaire garde le reste.
    expect(Vacation::query()->where('agent_id', $this->remplacant->id)->count())->toBe(2)
        ->and(Vacation::query()->where('agent_id', $this->titulaire->id)
            ->where('statut', 'planifiee')->count())->toBe(2);
});

it('découpe une ancienne nuit sur période autour d’un jour d’absence', function () {
    // Nuits du 01 au 04 (dernière relève le 05 au matin).
    auditVacation([
        'agent_id' => $this->titulaire->id,
        'site_id' => $this->site->id,
        'date_debut' => '2026-11-01',
        'date_fin' => '2026-11-05',
        'heure_debut' => '19:00',
        'heure_fin' => '07:00',
    ]);

    approuverAbsence($this, $this->titulaire->id, '2026-11-02', '2026-11-02');

    expect(auditPlanning())->toBe([
        'AU-1 2026-11-01→2026-11-02 planifiee',
        'AU-1 2026-11-02→2026-11-03 a_recouvrir',
        'AU-1 2026-11-03→2026-11-05 planifiee',
    ]);
});

it('rattache un contrôle à un seul créneau et compte « à recouvrir » absent', function () {
    $base = ['site_id' => $this->site->id, 'date_debut' => '2026-11-02'];
    auditVacation([...$base, 'agent_id' => $this->titulaire->id, 'date_fin' => '2026-11-02', 'statut' => 'a_recouvrir']);
    // Reconduction : jour puis nuit du même agent.
    auditVacation([...$base, 'agent_id' => $this->remplacant->id, 'date_fin' => '2026-11-02']);
    auditVacation([...$base, 'agent_id' => $this->remplacant->id, 'date_fin' => '2026-11-03', 'heure_debut' => '19:00', 'heure_fin' => '07:00']);

    Controle::query()->create([
        'site_id' => $this->site->id,
        'controle_agent_id' => $this->remplacant->id,
        'effectue_at' => '2026-11-02 19:30:00',
        'resultat' => 'present',
    ]);

    $lignes = collect((new PointageVacationsAction)->execute(Vacation::query()->get(), '2026-11-02', '2026-11-02'))
        ->map(fn (array $l) => $l['vacation']->agent_id === $this->titulaire->id
            ? 'titulaire '.$l['controle']
            : substr((string) $l['vacation']->heure_debut, 0, 5).' '.$l['controle'])
        ->sort()->values()->all();

    expect($lignes)->toBe([
        '07:00 non_controle',
        '19:00 present',
        'titulaire absent',
    ]);
});

it('découpe par jour une vacation étendue à une période en modification', function () {
    $v = auditVacation([
        'agent_id' => $this->titulaire->id,
        'site_id' => $this->site->id,
        'poste_id' => $this->poste->id,
        'date_debut' => '2026-11-02',
        'date_fin' => '2026-11-02',
    ]);

    $this->actingAs($this->admin)
        ->putJson("/api/v1/vacations/{$v->id}", ['date_fin' => '2026-11-04'])
        ->assertOk()
        ->assertJsonPath('meta.created', 2);

    expect(auditPlanning())->toBe([
        'AU-1 2026-11-02→2026-11-02 planifiee',
        'AU-1 2026-11-03→2026-11-03 planifiee',
        'AU-1 2026-11-04→2026-11-04 planifiee',
    ]);
});

it('ne découpe pas une ancienne période sur un simple changement de statut', function () {
    $v = auditVacation([
        'agent_id' => $this->titulaire->id,
        'site_id' => $this->site->id,
        'date_debut' => '2026-11-02',
        'date_fin' => '2026-11-10',
    ]);

    $this->actingAs($this->admin)
        ->putJson("/api/v1/vacations/{$v->id}", ['statut' => 'en_cours'])
        ->assertOk();

    expect(Vacation::query()->count())->toBe(1);
});

it('indique le jour du conflit lors d’une saisie sur période', function () {
    auditVacation([
        'agent_id' => $this->titulaire->id,
        'site_id' => $this->site->id,
        'date_debut' => '2026-11-04',
        'date_fin' => '2026-11-04',
        'heure_debut' => '10:00',
        'heure_fin' => '14:00',
    ]);

    $this->actingAs($this->admin)->postJson('/api/v1/vacations', [
        'agent_id' => $this->titulaire->id,
        'site_id' => $this->site->id,
        'poste_id' => $this->poste->id,
        'date_debut' => '2026-11-02',
        'date_fin' => '2026-11-06',
        'heure_debut' => '07:00',
        'heure_fin' => '19:00',
    ])->assertStatus(409)
        ->assertJsonPath('message', 'Cet agent a déjà une vacation sur ce créneau le 04/11/2026 (10:00–14:00, Site audit).');

    expect(Vacation::query()->count())->toBe(1);
});

it('laisse la proforma en attente si sa validation échoue', function () {
    $facture = Facture::query()->create([
        'numero' => 'SC-ABJ-2026-0099',
        'date_emission' => '2026-10-09',
        'montant_ht' => 1000,
        'montant_tva' => 0,
        'montant_ttc' => 1000,
        'devise' => 'XOF',
        'statut' => 'en_attente',
        'client_nom' => 'Sans périodicité',
    ]);

    $this->actingAs($this->admin)
        ->patchJson("/api/v1/factures/{$facture->id}/statut", ['statut' => 'valide'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('periodicite');

    expect($facture->fresh()->statut->value)->toBe('en_attente');
});

it('rattache les rondes au bon morceau après découpage pour absence', function () {
    $v = auditVacation([
        'agent_id' => $this->titulaire->id,
        'site_id' => $this->site->id,
        'date_debut' => '2026-11-01',
        'date_fin' => '2026-11-10',
    ]);
    $ronde = fn (?string $demarree, string $statut) => Ronde::query()->create([
        'agent_id' => $this->titulaire->id,
        'site_id' => $this->site->id,
        'vacation_id' => $v->id,
        'statut' => $statut,
        'demarree_at' => $demarree,
    ]);
    $avant = $ronde('2026-11-02 09:00:00', 'terminee');
    $apres = $ronde('2026-11-08 09:00:00', 'terminee');
    $planifiee = $ronde(null, 'planifiee');

    approuverAbsence($this, $this->titulaire->id, '2026-11-01', '2026-11-05');

    $trou = Vacation::query()->where('statut', 'a_recouvrir')->sole();
    $suite = Vacation::query()->where('statut', 'planifiee')->sole();

    expect($trou->id)->toBe($v->id)
        ->and($avant->fresh()->vacation_id)->toBe($trou->id)
        ->and($apres->fresh()->vacation_id)->toBe($suite->id)
        // Non démarrée : suit le morceau resté planifié, pas le trou.
        ->and($planifiee->fresh()->vacation_id)->toBe($suite->id);
});

it('expose une modification de nuit en sémantique stockée (relève le lendemain)', function () {
    $v = auditVacation([
        'agent_id' => $this->titulaire->id,
        'site_id' => $this->site->id,
        'date_debut' => '2026-11-02',
        'date_fin' => '2026-11-03',
        'heure_debut' => '19:00',
        'heure_fin' => '07:00',
    ]);

    // L’écran envoie « dernière nuit le 03 » → date_fin = 04 (relève du matin).
    $this->actingAs($this->admin)
        ->putJson("/api/v1/vacations/{$v->id}", [
            'date_debut' => '2026-11-02',
            'date_fin' => '2026-11-04',
            'heure_debut' => '19:00',
            'heure_fin' => '07:00',
        ])->assertOk()
        ->assertJsonPath('meta.created', 1);

    expect(auditPlanning())->toBe([
        'AU-1 2026-11-02→2026-11-03 planifiee',
        'AU-1 2026-11-03→2026-11-04 planifiee',
    ]);
});
