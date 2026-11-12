<?php

use App\Domain\Shared\Enums\TypeAgent;
use App\Models\Agent;
use App\Models\Client;
use App\Models\Contrat;
use App\Models\Grade;
use App\Models\Poste;
use App\Models\RondierPerimetre;
use App\Models\Site;
use App\Models\User;
use App\Models\Vacation;
use App\Models\Zone;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;

/** Le contrôleur règle l’absence sur le terrain, dans son périmètre et rien de plus. */
beforeEach(function () {
    // Mardi 10 novembre 2026, 19 h.
    Carbon::setTestNow('2026-11-10 19:00:00');
    $this->seed(RolePermissionSeeder::class);

    $grade = Grade::query()->create(['libelle' => 'Agent', 'type_agent' => TypeAgent::Agent]);
    $this->agent = fn (string $nom, array $attributs = []) => tap(Agent::query()->create([
        'grade_id' => $grade->id,
        'type' => TypeAgent::Agent,
        'nom' => $nom,
        'prenom' => 'Agent',
        'matricule' => 'C-'.$nom,
        'statut' => 'disponible',
        ...$attributs,
    ]), function (Agent $a) {
        Contrat::query()->where('agent_id', $a->id)->forceDelete();
        Contrat::query()->create(['agent_id' => $a->id, 'type' => 'cdi', 'date_debut' => '2026-01-01', 'statut' => 'actif']);
    });

    $client = Client::query()->create(['type' => 'entreprise', 'raison_sociale' => 'Client', 'statut' => 'actif']);
    $this->creerSite = fn (string $nom) => Site::query()->create([
        'client_id' => $client->id,
        'zone_id' => Zone::query()->create(['nom' => 'Zone '.$nom])->id,
        'nom' => $nom,
    ]);
    $this->site = ($this->creerSite)('Mien');
    $this->creerPoste = fn (Site $site) => Poste::query()->create([
        'site_id' => $site->id,
        'nom' => 'Entrée',
        'agents_requis' => 2,
        'heure_debut' => '06:00',
        'heure_fin' => '18:00',
        'heure_debut_nuit' => '18:00',
        'heure_fin_nuit' => '06:00',
    ]);
    $this->poste = ($this->creerPoste)($this->site);

    $this->user = User::factory()->create();
    $this->user->assignRole('controleur');
    $controleur = ($this->agent)('Controleur', ['type' => TypeAgent::Controleur, 'user_id' => $this->user->id]);
    RondierPerimetre::query()->create(['agent_id' => $controleur->id, 'site_id' => $this->site->id]);

    $this->poser = fn (Agent $a, string $date, string $debut, string $fin, array $attributs = [], ?Poste $poste = null) => Vacation::query()->create([
        'agent_id' => $a->id,
        'site_id' => ($poste ?? $this->poste)->site_id,
        'poste_id' => ($poste ?? $this->poste)->id,
        'date_debut' => $date,
        'date_fin' => $fin <= $debut ? Carbon::parse($date)->addDay()->toDateString() : $date,
        'heure_debut' => $debut,
        'heure_fin' => $fin,
        'statut' => 'planifiee',
        ...$attributs,
    ]);

    $this->jour = ($this->agent)('Jour');
    $this->absent = ($this->agent)('Absent');
    ($this->poser)($this->jour, '2026-11-10', '06:00', '18:00');
    $this->trou = ($this->poser)($this->absent, '2026-11-10', '18:00', '06:00', ['statut' => 'a_recouvrir']);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('laisse le contrôleur mettre l’agent qui descend sur la nuit du jour, en heures supplémentaires', function () {
    $this->actingAs($this->user)
        ->postJson("/api/v1/vacations/{$this->trou->id}/recouvrir", ['agent_id' => $this->jour->id])
        ->assertCreated()
        ->assertJsonPath('data.agent_id', $this->jour->id)
        ->assertJsonPath('data.heures_supplementaires', true);

    expect($this->trou->fresh()->statut->value)->toBe('annulee');
});

it('propose l’agent qui descend en premier, sans les agents occupés, absents ou sans contrat', function () {
    $libre = ($this->agent)('Libre');
    $occupe = ($this->agent)('Occupe');
    ($this->poser)($occupe, '2026-11-10', '20:00', '23:00', [], ($this->creerPoste)(($this->creerSite)('Autre')));
    $sansContrat = ($this->agent)('SansContrat');
    Contrat::query()->where('agent_id', $sansContrat->id)->forceDelete();
    $conge = ($this->agent)('Conge', ['statut' => 'conge']);

    $noms = $this->actingAs($this->user)
        ->getJson("/api/v1/vacations/{$this->trou->id}/remplacants")
        ->assertOk()
        ->assertJsonPath('data.0.agent_id', $this->jour->id)
        ->assertJsonPath('data.0.descend', true)
        ->json('data.*.agent_id');

    expect($noms)->toContain($libre->id)
        ->not->toContain($occupe->id)
        ->not->toContain($sansContrat->id)
        ->not->toContain($conge->id)
        ->not->toContain($this->absent->id);
});

it('refuse un agent déjà pris sur le créneau', function () {
    $occupe = ($this->agent)('Occupe');
    ($this->poser)($occupe, '2026-11-10', '20:00', '23:00', [], ($this->creerPoste)(($this->creerSite)('Autre')));

    $this->actingAs($this->user)
        ->postJson("/api/v1/vacations/{$this->trou->id}/recouvrir", ['agent_id' => $occupe->id])
        ->assertUnprocessable();
});

it('refuse une case hors de son périmètre', function () {
    $poste = ($this->creerPoste)(($this->creerSite)('Autre'));
    $trou = ($this->poser)(($this->agent)('Ailleurs'), '2026-11-10', '18:00', '06:00', ['statut' => 'a_recouvrir'], $poste);

    $this->actingAs($this->user)->postJson("/api/v1/vacations/{$trou->id}/recouvrir", ['agent_id' => $this->jour->id])->assertForbidden();
    $this->actingAs($this->user)->getJson("/api/v1/vacations/{$trou->id}/remplacants")->assertForbidden();
});

it('refuse une case d’un autre jour, qui reste au planning', function () {
    $demain = ($this->poser)($this->absent, '2026-11-12', '18:00', '06:00', ['statut' => 'a_recouvrir']);

    $this->actingAs($this->user)->postJson("/api/v1/vacations/{$demain->id}/recouvrir", ['agent_id' => $this->jour->id])->assertForbidden();
});

it('accepte encore le matin la nuit commencée la veille', function () {
    Carbon::setTestNow('2026-11-11 05:00:00');

    $this->actingAs($this->user)
        ->postJson("/api/v1/vacations/{$this->trou->id}/recouvrir", ['agent_id' => $this->jour->id])
        ->assertCreated();
});

it('ne laisse pas le contrôleur décider seul des heures supplémentaires', function () {
    $libre = ($this->agent)('Libre');

    $this->actingAs($this->user)
        ->postJson("/api/v1/vacations/{$this->trou->id}/recouvrir", ['agent_id' => $libre->id, 'heures_supplementaires' => true])
        ->assertCreated()
        ->assertJsonPath('data.heures_supplementaires', false);
});

it('ne donne au contrôleur aucun autre droit sur le planning', function () {
    $v = Vacation::query()->where('agent_id', $this->jour->id)->sole();

    $this->actingAs($this->user)->putJson("/api/v1/vacations/{$v->id}", ['agent_id' => $this->absent->id])->assertForbidden();
    $this->actingAs($this->user)->deleteJson("/api/v1/vacations/{$v->id}")->assertForbidden();
    $this->actingAs($this->user)->postJson('/api/v1/vacations/bulk', ['vacations' => [[
        'agent_id' => $this->absent->id,
        'site_id' => $this->site->id,
        'poste_id' => $this->poste->id,
        'date_debut' => '2026-11-12',
        'date_fin' => '2026-11-12',
        'heure_debut' => '06:00',
        'heure_fin' => '18:00',
    ]]])->assertForbidden();
});

it('n’accorde rien au rôle agent', function () {
    $user = User::factory()->create();
    $user->assignRole('agent');
    ($this->agent)('Poste', ['user_id' => $user->id]);

    $this->actingAs($user)->postJson("/api/v1/vacations/{$this->trou->id}/recouvrir", ['agent_id' => $this->jour->id])->assertForbidden();
});

it('garde les droits complets du planificateur', function () {
    $admin = User::factory()->create();
    $admin->assignRole('operation');
    $demain = ($this->poser)($this->absent, '2026-11-12', '18:00', '06:00', ['statut' => 'a_recouvrir']);

    $this->actingAs($admin)
        ->postJson("/api/v1/vacations/{$demain->id}/recouvrir", ['agent_id' => ($this->agent)('Libre')->id, 'heures_supplementaires' => true])
        ->assertCreated()
        ->assertJsonPath('data.heures_supplementaires', true);
});
