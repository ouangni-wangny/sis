<?php

use App\Domain\Shared\Enums\StatutPeriodePaie;
use App\Domain\Shared\Enums\StatutVacation;
use App\Domain\Shared\Enums\TypeAgent;
use App\Models\Agent;
use App\Models\BulletinPaie;
use App\Models\Client;
use App\Models\Contrat;
use App\Models\Controle;
use App\Models\Grade;
use App\Models\PeriodePaie;
use App\Models\Qualification;
use App\Models\Site;
use App\Models\User;
use App\Models\Vacation;
use App\Models\Zone;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->rh = User::factory()->create(['email' => 'rh-primes@sis.ci']);
    $this->rh->assignRole('rh');

    $grade = Grade::query()->create(['libelle' => 'Agent', 'type_agent' => TypeAgent::Agent]);
    $this->agent = Agent::query()->create([
        'grade_id' => $grade->id,
        'type' => TypeAgent::Agent,
        'nom' => 'Prime',
        'prenom' => 'Agent',
        'matricule' => 'AG-PR1',
        'statut' => 'en_activite',
    ]);
    Contrat::query()->where('agent_id', $this->agent->id)->forceDelete();
    Contrat::query()->create([
        'agent_id' => $this->agent->id,
        'type' => 'cdi',
        'date_debut' => '2025-01-01',
        'statut' => 'actif',
        'salaire_base' => 100000,
    ]);

    $this->cp = Qualification::query()->where('abreviation', 'CP')->firstOrFail();
    $this->ga = Qualification::query()->where('abreviation', 'GA')->firstOrFail();
    $this->cp->primes()->create(['montant' => 10000, 'unite' => 'mois']);
    $this->ga->primes()->create(['montant' => 500, 'unite' => 'jour']);

    // Novembre 2026 : mois de pointage du 26/10 au 25/11.
    $this->novembre = PeriodePaie::query()->create([
        'mois' => 11,
        'annee' => 2026,
        'date_debut' => '2026-11-01',
        'date_fin' => '2026-11-30',
        'statut' => StatutPeriodePaie::Brouillon,
    ]);
});

it('rattache des qualifications à un agent', function () {
    $this->actingAs($this->rh)
        ->putJson("/api/v1/agents/{$this->agent->id}/qualifications", ['qualification_ids' => [$this->cp->id, $this->ga->id]])
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $this->actingAs($this->rh)
        ->getJson("/api/v1/agents/{$this->agent->id}/qualifications")
        ->assertOk()
        ->assertJsonFragment(['abreviation' => 'CP']);

    $this->actingAs($this->rh)
        ->putJson("/api/v1/agents/{$this->agent->id}/qualifications", ['qualification_ids' => []])
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('ajoute les primes au bulletin : par mois fixe, par jour × jours pointés', function () {
    $this->agent->qualifications()->sync([$this->cp->id, $this->ga->id]);

    $client = Client::query()->create(['type' => 'entreprise', 'raison_sociale' => 'Client PR', 'statut' => 'actif']);
    $zone = Zone::query()->create(['nom' => 'Zone PR']);
    $site = Site::query()->create(['client_id' => $client->id, 'zone_id' => $zone->id, 'nom' => 'Site PR']);

    // 3 jours dans le mois de pointage (dont le 26/10), 1 hors période (26/11).
    foreach (['2026-10-26', '2026-11-02', '2026-11-03', '2026-11-26'] as $jour) {
        Vacation::query()->create([
            'agent_id' => $this->agent->id,
            'site_id' => $site->id,
            'date_debut' => $jour,
            'date_fin' => $jour,
            'heure_debut' => '07:00',
            'heure_fin' => '19:00',
            'statut' => StatutVacation::Planifiee->value,
        ]);
        Controle::query()->create([
            'site_id' => $site->id,
            'controle_agent_id' => $this->agent->id,
            'effectue_at' => $jour.' 10:00:00',
            'resultat' => 'present',
        ]);
    }

    $this->actingAs($this->rh)
        ->postJson("/api/v1/periodes-paie/{$this->novembre->id}/generer-bulletins")
        ->assertOk();

    $bulletin = BulletinPaie::query()->where('agent_id', $this->agent->id)->firstOrFail();
    $primes = collect($bulletin->details['rubriques']['primes'])->keyBy('abreviation');

    expect($primes['CP']['montant'])->toEqual(10000)
        ->and($primes['GA']['quantite'])->toBe(3)
        ->and($primes['GA']['montant'])->toEqual(1500)
        ->and($bulletin->details['jours_pointes'])->toBe(3)
        // Brut = base 100 000 + CP 10 000 + GA 1 500.
        ->and((float) $bulletin->salaire_brut)->toEqual(111500.0);
});

it('ignore les qualifications et primes inactives', function () {
    $this->agent->qualifications()->sync([$this->cp->id]);
    $this->cp->update(['actif' => false]);

    $this->actingAs($this->rh)
        ->postJson("/api/v1/periodes-paie/{$this->novembre->id}/generer-bulletins")
        ->assertOk();

    $bulletin = BulletinPaie::query()->where('agent_id', $this->agent->id)->firstOrFail();
    expect($bulletin->details['rubriques']['primes'])->toBe([])
        ->and((float) $bulletin->salaire_brut)->toEqual(100000.0);
});

it('affiche les primes sur le PDF du bulletin', function () {
    $this->agent->qualifications()->sync([$this->cp->id]);
    $this->actingAs($this->rh)
        ->postJson("/api/v1/periodes-paie/{$this->novembre->id}/generer-bulletins")
        ->assertOk();

    $bulletin = BulletinPaie::query()->where('agent_id', $this->agent->id)->firstOrFail();
    $html = view('pdf.bulletin-paie', ['bulletin' => $bulletin->load('agent.grade', 'contrat', 'periodePaie'), 'generatedAt' => 'test'])->render();

    expect($html)->toContain('Prime Chef de poste (CP)')->toContain('10 000');
});

it('enregistre les qualifications depuis le formulaire du personnel (facultatives)', function () {
    $grade = Grade::query()->first();
    $payload = ['grade_id' => $grade->id, 'nom' => 'Sans', 'prenom' => 'Qualif'];

    // Sans qualification : accepté.
    $this->actingAs($this->rh)
        ->postJson('/api/v1/agents', $payload)
        ->assertCreated();

    $id = $this->actingAs($this->rh)
        ->postJson('/api/v1/agents', [...$payload, 'prenom' => 'Avec', 'qualification_ids' => [$this->ga->id]])
        ->assertCreated()
        ->assertJsonPath('data.qualifications.0.abreviation', 'GA')
        ->json('data.id');

    // Modification : la liste envoyée remplace la précédente (vide = aucune).
    $this->actingAs($this->rh)
        ->putJson("/api/v1/agents/{$id}", ['qualification_ids' => [$this->cp->id]])
        ->assertOk()
        ->assertJsonPath('data.qualifications.0.abreviation', 'CP');
    $this->actingAs($this->rh)
        ->putJson("/api/v1/agents/{$id}", ['qualification_ids' => []])
        ->assertOk()
        ->assertJsonCount(0, 'data.qualifications');
});

it('filtre les bulletins de la période par qualification', function () {
    $this->agent->qualifications()->sync([$this->cp->id]);
    $autre = Agent::query()->create([
        'grade_id' => $this->agent->grade_id,
        'type' => TypeAgent::Agent,
        'nom' => 'Sans',
        'prenom' => 'Qualification',
        'matricule' => 'AG-PR2',
        'statut' => 'en_activite',
    ]);
    Contrat::query()->where('agent_id', $autre->id)->forceDelete();
    Contrat::query()->create([
        'agent_id' => $autre->id,
        'type' => 'cdi',
        'date_debut' => '2025-01-01',
        'statut' => 'actif',
        'salaire_base' => 90000,
    ]);

    $this->actingAs($this->rh)
        ->postJson("/api/v1/periodes-paie/{$this->novembre->id}/generer-bulletins")
        ->assertOk();

    $this->actingAs($this->rh)
        ->getJson("/api/v1/periodes-paie/{$this->novembre->id}/bulletins?qualification_id={$this->cp->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.agent_id', $this->agent->id);

    $this->actingAs($this->rh)
        ->getJson("/api/v1/periodes-paie/{$this->novembre->id}/bulletins?qualification_id={$this->ga->id}")
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this->actingAs($this->rh)
        ->get("/api/v1/periodes-paie/{$this->novembre->id}/bulletins/export-pdf?qualification_id={$this->cp->id}")
        ->assertOk();
});
