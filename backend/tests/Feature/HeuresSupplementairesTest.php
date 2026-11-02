<?php

use App\Domain\Shared\Enums\ResultatControle;
use App\Domain\Shared\Enums\TypeAgent;
use App\Models\Absence;
use App\Models\Agent;
use App\Models\Client;
use App\Models\Contrat;
use App\Models\Controle;
use App\Models\Grade;
use App\Models\Poste;
use App\Models\Site;
use App\Models\User;
use App\Models\Vacation;
use App\Models\Zone;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('super-admin');

    $grade = Grade::query()->create(['libelle' => 'Agent', 'type_agent' => TypeAgent::Agent]);
    $this->creerAgent = function (string $nom) use ($grade): Agent {
        $agent = Agent::query()->create([
            'grade_id' => $grade->id,
            'type' => TypeAgent::Agent,
            'nom' => $nom,
            'prenom' => 'Agent',
            'matricule' => 'HS-'.$nom,
            'statut' => 'disponible',
        ]);
        Contrat::query()->where('agent_id', $agent->id)->forceDelete();
        Contrat::query()->create(['agent_id' => $agent->id, 'type' => 'cdi', 'date_debut' => '2026-01-01', 'statut' => 'actif']);

        return $agent;
    };

    $this->descendant = ($this->creerAgent)('Descendant');
    $this->absent = ($this->creerAgent)('Absent');
    $this->autre = ($this->creerAgent)('Autre');

    $client = Client::query()->create(['type' => 'entreprise', 'raison_sociale' => 'Client', 'statut' => 'actif']);
    $zone = Zone::query()->create(['nom' => 'Zone']);
    $this->site = Site::query()->create(['client_id' => $client->id, 'zone_id' => $zone->id, 'nom' => 'Site']);
    $this->poste = Poste::query()->create([
        'site_id' => $this->site->id,
        'nom' => 'Poste',
        'agents_requis' => 2,
        'heure_debut' => '06:00',
        'heure_fin' => '06:00',
    ]);

    $this->vacation = fn (Agent $agent, string $debut, string $fin, string $date = '2026-08-10', string $statut = 'planifiee') => Vacation::query()->create([
        'agent_id' => $agent->id,
        'site_id' => $this->site->id,
        'poste_id' => $this->poste->id,
        'date_debut' => $date,
        'date_fin' => $fin < $debut ? date('Y-m-d', strtotime($date.' +1 day')) : $date,
        'heure_debut' => $debut,
        'heure_fin' => $fin,
        'statut' => $statut,
    ]);

    // L’agent de jour descend à 18h ; celui de nuit, absent, devait prendre la relève.
    $this->jour = ($this->vacation)($this->descendant, '06:00', '18:00');
    $this->nuit = ($this->vacation)($this->absent, '18:00', '06:00');

    $this->declarerAbsence = fn () => $this->actingAs($this->admin)->postJson('/api/v1/absences', [
        'agent_id' => $this->absent->id,
        'type' => 'autre',
        'date_debut' => '2026-08-10',
        'date_fin' => '2026-08-10',
        'motif' => 'Ne s’est pas présenté',
        'statut' => 'approuvee',
    ])->assertCreated();

    $this->recouvrir = fn (Agent $agent, array $extra = []) => $this->actingAs($this->admin)
        ->postJson("/api/v1/vacations/{$this->nuit->id}/recouvrir", ['agent_id' => $agent->id, ...$extra]);

    $this->presence = fn (Agent $agent, string $quand) => Controle::query()->create([
        'controle_agent_id' => $agent->id,
        'site_id' => $this->site->id,
        'effectue_at' => $quand,
        'resultat' => ResultatControle::Present,
    ]);
});

it('propose l’agent qui descend pour le créneau à recouvrir', function () {
    ($this->declarerAbsence)();

    $this->actingAs($this->admin)
        ->getJson("/api/v1/vacations/{$this->nuit->id}/agents-descendants")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.agent_id', $this->descendant->id)
        ->assertJsonPath('data.0.heure_fin', '18:00')
        ->assertJsonPath('data.0.meme_poste', true);
});

it('ne propose pas un agent qui ne finit pas à ce moment-là', function () {
    ($this->vacation)($this->autre, '08:00', '12:00');
    ($this->declarerAbsence)();

    $this->actingAs($this->admin)
        ->getJson("/api/v1/vacations/{$this->nuit->id}/agents-descendants")
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.agent_id', $this->descendant->id);
});

it('trouve l’agent de nuit qui descend le matin, sur une vacation de la veille', function () {
    $nuitVeille = ($this->vacation)($this->autre, '18:00', '06:00', '2026-08-09');
    expect($nuitVeille->exists)->toBeTrue();

    $trouMatin = ($this->vacation)($this->absent, '06:00', '18:00', '2026-08-10', 'a_recouvrir');

    $ids = collect($this->actingAs($this->admin)
        ->getJson("/api/v1/vacations/{$trouMatin->id}/agents-descendants")
        ->assertOk()
        ->json('data'))->pluck('agent_id');

    expect($ids)->toContain($this->autre->id);
});

it('laisse l’agent qui descend continuer, en heures supplémentaires, malgré le repos légal', function () {
    ($this->declarerAbsence)();

    ($this->recouvrir)($this->descendant)
        ->assertCreated()
        ->assertJsonPath('data.agent_id', $this->descendant->id)
        ->assertJsonPath('data.heures_supplementaires', true);

    expect($this->nuit->fresh()->statut->value)->toBe('annulee')
        ->and($this->jour->fresh()->statut->value)->toBe('planifiee')
        ->and(Vacation::query()->where('agent_id', $this->descendant->id)->count())->toBe(2);
});

it('ne marque pas d’heures supplémentaires pour un remplaçant au repos, sauf si demandé', function () {
    ($this->declarerAbsence)();

    ($this->recouvrir)($this->autre)
        ->assertCreated()
        ->assertJsonPath('data.heures_supplementaires', false);
});

it('marque les heures supplémentaires d’un remplaçant au repos quand c’est demandé', function () {
    ($this->declarerAbsence)();

    ($this->recouvrir)($this->autre, ['heures_supplementaires' => true])
        ->assertCreated()
        ->assertJsonPath('data.heures_supplementaires', true);
});

it('refuse toujours un agent déjà occupé sur le créneau', function () {
    ($this->vacation)($this->autre, '20:00', '23:00');
    ($this->declarerAbsence)();

    ($this->recouvrir)($this->autre)->assertStatus(409);
});

it('ne marque pas les vacations normales en heures supplémentaires', function () {
    expect($this->jour->fresh()->heures_supplementaires)->toBeFalse();
});

it('compte les heures supplémentaires au pointage quand la présence est contrôlée', function () {
    ($this->declarerAbsence)();
    ($this->recouvrir)($this->descendant)->assertCreated();
    ($this->presence)($this->descendant, '2026-08-10 09:00:00');
    ($this->presence)($this->descendant, '2026-08-10 22:00:00');

    $reponse = $this->actingAs($this->admin)
        ->getJson('/api/v1/vacations/pointage?date_debut=2026-08-10&date_fin=2026-08-10&agent_id='.$this->descendant->id)
        ->assertOk();

    expect($reponse->json('meta.present'))->toBe(2)
        ->and((float) $reponse->json('meta.heures_comptees'))->toBe(24.0)
        ->and((float) $reponse->json('meta.heures_supplementaires'))->toBe(12.0)
        ->and($reponse->json('meta.jours_supplementaires'))->toBe(1);
});

it('ne compte pas d’heures supplémentaires sans contrôle de présence', function () {
    ($this->declarerAbsence)();
    ($this->recouvrir)($this->descendant)->assertCreated();

    $this->actingAs($this->admin)
        ->getJson('/api/v1/vacations/pointage?date_debut=2026-08-10&date_fin=2026-08-10&agent_id='.$this->descendant->id)
        ->assertJsonPath('meta.jours_supplementaires', 0);
});

it('garde l’absence du titulaire après son remplacement', function () {
    ($this->declarerAbsence)();
    ($this->recouvrir)($this->descendant)->assertCreated();

    $this->actingAs($this->admin)
        ->getJson('/api/v1/vacations/pointage?date_debut=2026-08-10&date_fin=2026-08-10&agent_id='.$this->absent->id)
        ->assertOk()
        ->assertJsonPath('meta.absent', 1)
        ->assertJsonPath('data.0.controle', 'absent');
});

it('ne compte pas une vacation simplement annulée comme une absence', function () {
    $this->nuit->update(['statut' => 'annulee']);

    $this->actingAs($this->admin)
        ->getJson('/api/v1/vacations/pointage?date_debut=2026-08-10&date_fin=2026-08-10&agent_id='.$this->absent->id)
        ->assertJsonPath('meta.absent', 0)
        ->assertJsonCount(0, 'data');
});

it('ne compte plus l’absence si elle a été annulée', function () {
    ($this->declarerAbsence)();
    ($this->recouvrir)($this->descendant)->assertCreated();
    Absence::query()->where('agent_id', $this->absent->id)->update(['statut' => 'annulee']);

    $this->actingAs($this->admin)
        ->getJson('/api/v1/vacations/pointage?date_debut=2026-08-10&date_fin=2026-08-10&agent_id='.$this->absent->id)
        ->assertJsonPath('meta.absent', 0);
});

it('donne présences, absences et jours supplémentaires de tous les agents', function () {
    ($this->declarerAbsence)();
    ($this->recouvrir)($this->descendant)->assertCreated();
    ($this->presence)($this->descendant, '2026-08-10 09:00:00');
    ($this->presence)($this->descendant, '2026-08-10 22:00:00');

    $lignes = collect($this->actingAs($this->admin)
        ->getJson('/api/v1/vacations/pointage/synthese?date_debut=2026-08-10&date_fin=2026-08-10')
        ->assertOk()
        ->json('data'))->keyBy('agent_id');

    expect($lignes[$this->descendant->id])->toMatchArray([
        'presences' => 2,
        'absences' => 0,
        'jours_supplementaires' => 1,
    ])
        ->and((float) $lignes[$this->descendant->id]['heures_supplementaires'])->toBe(12.0)
        ->and($lignes[$this->absent->id])->toMatchArray(['presences' => 0, 'absences' => 1, 'jours_supplementaires' => 0])
        // Agent sans créneau sur la période : affiché à zéro.
        ->and($lignes[$this->autre->id])->toMatchArray(['presences' => 0, 'absences' => 0, 'non_controles' => 0]);
});

it('limite la synthèse aux agents du filtre', function () {
    $this->actingAs($this->admin)
        ->getJson('/api/v1/vacations/pointage/synthese?date_debut=2026-08-10&date_fin=2026-08-10&agent_id='.$this->descendant->id)
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $parSite = collect($this->actingAs($this->admin)
        ->getJson('/api/v1/vacations/pointage/synthese?date_debut=2026-08-10&date_fin=2026-08-10&site_id='.$this->site->id)
        ->json('data'))->pluck('agent_id');

    expect($parSite)->not->toContain($this->autre->id);
});

it('refuse la synthèse sans le droit de voir le planning', function () {
    $this->actingAs(User::factory()->create())
        ->getJson('/api/v1/vacations/pointage/synthese?date_debut=2026-08-10&date_fin=2026-08-10')
        ->assertForbidden();
});

it('refuse de proposer ou recouvrir sans le droit de modifier', function () {
    ($this->declarerAbsence)();
    $lecteur = User::factory()->create();

    $this->actingAs($lecteur)->getJson("/api/v1/vacations/{$this->nuit->id}/agents-descendants")->assertForbidden();
    $this->actingAs($lecteur)->postJson("/api/v1/vacations/{$this->nuit->id}/recouvrir", ['agent_id' => $this->descendant->id])->assertForbidden();
});
