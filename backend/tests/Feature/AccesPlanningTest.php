<?php

use App\Domain\Shared\Enums\TypeAgent;
use App\Models\Agent;
use App\Models\Client;
use App\Models\Contrat;
use App\Models\Controle;
use App\Models\Grade;
use App\Models\Poste;
use App\Models\RondierPerimetre;
use App\Models\Site;
use App\Models\User;
use App\Models\Vacation;
use App\Models\Zone;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;

/**
 * Qui lit quoi dans le planning, et ce qui reste modifiable une fois la vacation effectuée.
 */
beforeEach(function () {
    // Mardi 10 novembre 2026, 19 h.
    Carbon::setTestNow('2026-11-10 19:00:00');
    $this->seed(RolePermissionSeeder::class);

    $grade = Grade::query()->create(['libelle' => 'Agent', 'type_agent' => TypeAgent::Agent]);
    $this->agent = fn (string $nom, array $attributs = []) => tap(Agent::query()->create([
        'grade_id' => $grade->id,
        'type' => TypeAgent::Agent,
        'nom' => $nom,
        'prenom' => 'Awa',
        'matricule' => 'M-'.$nom,
        'statut' => 'disponible',
        ...$attributs,
    ]), function (Agent $a) {
        Contrat::query()->where('agent_id', $a->id)->forceDelete();
        Contrat::query()->create(['agent_id' => $a->id, 'type' => 'cdi', 'date_debut' => '2026-01-01', 'statut' => 'actif']);
    });
    $this->compte = fn (string $role) => tap(User::factory()->create(), fn (User $u) => $u->assignRole($role));

    $client = Client::query()->create(['type' => 'entreprise', 'raison_sociale' => 'Client', 'statut' => 'actif']);
    $creerPoste = function (string $nom) use ($client) {
        $site = Site::query()->create([
            'client_id' => $client->id,
            'zone_id' => Zone::query()->create(['nom' => 'Zone '.$nom])->id,
            'nom' => $nom,
        ]);

        return Poste::query()->create([
            'site_id' => $site->id,
            'nom' => 'Entrée',
            'agents_requis' => 2,
            'heure_debut' => '06:00',
            'heure_fin' => '18:00',
            'heure_debut_nuit' => '18:00',
            'heure_fin_nuit' => '06:00',
        ]);
    };
    $this->poste = $creerPoste('Mien');
    $this->autrePoste = $creerPoste('Autre');

    $this->poser = fn (Agent $a, string $date, array $attributs = [], ?Poste $poste = null) => Vacation::query()->create([
        'agent_id' => $a->id,
        'site_id' => ($poste ?? $this->poste)->site_id,
        'poste_id' => ($poste ?? $this->poste)->id,
        'date_debut' => $date,
        'date_fin' => $date,
        'heure_debut' => '06:00',
        'heure_fin' => '18:00',
        'statut' => 'planifiee',
        ...$attributs,
    ]);
    $this->presence = fn (Vacation $v, string $resultat = 'present') => Controle::query()->create([
        'agent_id' => ($this->agent)('Ctrl'.$resultat.$v->id, ['type' => TypeAgent::Controleur])->id,
        'site_id' => $v->site_id,
        'poste_id' => $v->poste_id,
        'controle_agent_id' => $v->agent_id,
        'effectue_at' => '2026-11-10 09:00:00',
        'resultat' => $resultat,
    ]);

    $this->moi = ($this->agent)('Moi');
    $this->collegue = ($this->agent)('Collegue');
    $this->maVacation = ($this->poser)($this->moi, '2026-11-12');
    $this->sienne = ($this->poser)($this->collegue, '2026-11-12', [], $this->autrePoste);
});

afterEach(function () {
    Carbon::setTestNow();
});

$idsVus = fn ($reponse) => collect($reponse->assertOk()->json('data'))->pluck('id')->all();

it('ne montre à l’agent que ses vacations', function () use ($idsVus) {
    $user = ($this->compte)('agent');
    $this->moi->update(['user_id' => $user->id]);

    expect($idsVus($this->actingAs($user)->getJson('/api/v1/vacations?all=1')))->toBe([$this->maVacation->id]);

    $this->getJson("/api/v1/vacations/{$this->maVacation->id}")->assertOk();
    $this->getJson("/api/v1/vacations/{$this->sienne->id}")
        ->assertForbidden()
        ->assertJsonPath('message', 'Vous n’avez pas le droit d’effectuer cette action.');

    $pointage = $this->getJson('/api/v1/vacations/pointage?date_debut=2026-11-12&date_fin=2026-11-12')->assertOk()->json('data');
    expect(collect($pointage)->pluck('agent_id')->unique()->all())->toBe([$this->moi->id]);

    $synthese = $this->getJson('/api/v1/vacations/pointage/synthese?date_debut=2026-11-12&date_fin=2026-11-12')->assertOk()->json('data');
    expect(collect($synthese)->pluck('agent_id')->all())->toBe([$this->moi->id]);
});

it('ne montre rien à un compte terrain sans fiche agent', function (string $role) use ($idsVus) {
    expect($idsVus($this->actingAs(($this->compte)($role))->getJson('/api/v1/vacations?all=1')))->toBe([]);
})->with(['agent', 'controleur']);

it('limite le contrôleur à son périmètre, y compris sur une vacation ouverte directement', function () use ($idsVus) {
    $user = ($this->compte)('controleur');
    $fiche = ($this->agent)('Controleur', ['type' => TypeAgent::Controleur, 'user_id' => $user->id]);
    RondierPerimetre::query()->create(['agent_id' => $fiche->id, 'site_id' => $this->poste->site_id]);

    expect($idsVus($this->actingAs($user)->getJson('/api/v1/vacations?all=1')))->toBe([$this->maVacation->id]);
    $this->getJson("/api/v1/vacations/{$this->sienne->id}")->assertForbidden();
});

it('laisse le planning et l’administration tout voir', function (string $role) {
    $reponse = $this->actingAs(($this->compte)($role))->getJson('/api/v1/vacations?all=1')->assertOk();

    expect($reponse->json('data'))->toHaveCount(2);
})->with(['super-admin', 'operation', 'administration']);

it('réserve la recherche de conflits à ceux qui planifient', function () {
    $requete = 'agent_id='.$this->moi->id.'&date_debut=2026-11-12&heure_debut=06:00&heure_fin=18:00';

    $this->actingAs(($this->compte)('agent'))->getJson("/api/v1/vacations/conflits?{$requete}")->assertForbidden();
    $this->actingAs(($this->compte)('rh'))->getJson("/api/v1/vacations/conflits?{$requete}")->assertForbidden();
    $this->actingAs(($this->compte)('operation'))->getJson("/api/v1/vacations/conflits?{$requete}")->assertOk();
});

it('refuse de changer une vacation pointée présent, sauf au super-admin', function () {
    $pointee = ($this->poser)($this->collegue, '2026-11-10');
    ($this->presence)($pointee);
    $message = 'Cette vacation ne peut plus être %s : un contrôle a déjà constaté la présence de l’agent, et elle compte pour la paie. Contactez un super-admin.';

    $operation = ($this->compte)('operation');
    expect($this->actingAs($operation)->putJson("/api/v1/vacations/{$pointee->id}", ['heure_fin' => '17:00'])->assertStatus(422)->json('errors.vacation.0'))
        ->toBe(sprintf($message, 'modifiée'));
    expect($this->deleteJson("/api/v1/vacations/{$pointee->id}")->assertStatus(422)->json('errors.vacation.0'))
        ->toBe(sprintf($message, 'supprimée'));

    $this->actingAs(($this->compte)('super-admin'))->deleteJson("/api/v1/vacations/{$pointee->id}")->assertNoContent();
});

it('laisse remplacer l’agent contrôlé absent', function () {
    $absent = ($this->poser)($this->collegue, '2026-11-10');
    ($this->presence)($absent, 'absent');

    $this->actingAs(($this->compte)('operation'))
        ->putJson("/api/v1/vacations/{$absent->id}", ['agent_id' => ($this->agent)('Remplacant')->id])
        ->assertOk();
});

it('refuse de changer une vacation terminée et ne laisse que la clôture d’une vacation en cours', function () {
    $operation = ($this->compte)('operation');
    $terminee = ($this->poser)($this->collegue, '2026-11-01', ['statut' => 'terminee']);
    $enCours = ($this->poser)($this->moi, '2026-11-10', ['statut' => 'en_cours']);

    $this->actingAs($operation)->putJson("/api/v1/vacations/{$terminee->id}", ['heure_fin' => '17:00'])->assertStatus(422);
    $this->deleteJson("/api/v1/vacations/{$terminee->id}")->assertStatus(422);
    $this->putJson("/api/v1/vacations/{$enCours->id}", ['heure_fin' => '17:00'])->assertStatus(422);
    $this->deleteJson("/api/v1/vacations/{$enCours->id}")->assertStatus(422);

    $this->putJson("/api/v1/vacations/{$enCours->id}", ['statut' => 'terminee'])->assertOk();
});

it('garde en replanifiant le créneau du jour déjà pointé présent', function () {
    $pointee = ($this->poser)($this->collegue, '2026-11-10');
    ($this->presence)($pointee);

    $this->actingAs(($this->compte)('operation'))->postJson('/api/v1/vacations/bulk', [
        'vacations' => [[
            'agent_id' => $this->moi->id,
            'site_id' => $this->poste->site_id,
            'poste_id' => $this->poste->id,
            'date_debut' => '2026-11-11',
            'date_fin' => '2026-11-11',
            'heure_debut' => '06:00',
            'heure_fin' => '18:00',
        ]],
        'replace' => ['poste_id' => $this->poste->id, 'date_debut' => '2026-11-10', 'date_fin' => '2026-11-30', 'scope' => 'poste'],
    ])->assertCreated();

    expect(Vacation::query()->whereKey($pointee->id)->exists())->toBeTrue();
});
