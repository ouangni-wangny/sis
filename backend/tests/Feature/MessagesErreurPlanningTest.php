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
use Illuminate\Support\Facades\Route;

/**
 * Ce que l’utilisateur lit quand le planning refuse : en français, sans détail
 * technique, en disant de quel agent et de quel jour il s’agit.
 */
beforeEach(function () {
    // Mardi 10 novembre 2026, 19 h.
    Carbon::setTestNow('2026-11-10 19:00:00');
    $this->seed(RolePermissionSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('super-admin');

    $grade = Grade::query()->create(['libelle' => 'Agent', 'type_agent' => TypeAgent::Agent]);
    $this->agent = fn (string $nom, array $attributs = [], bool $contrat = true) => tap(Agent::query()->create([
        'grade_id' => $grade->id,
        'type' => TypeAgent::Agent,
        'nom' => $nom,
        'prenom' => 'Awa',
        'matricule' => 'M-'.$nom,
        'statut' => 'disponible',
        ...$attributs,
    ]), function (Agent $a) use ($contrat) {
        Contrat::query()->where('agent_id', $a->id)->forceDelete();
        if ($contrat) {
            Contrat::query()->create(['agent_id' => $a->id, 'type' => 'cdi', 'date_debut' => '2026-01-01', 'statut' => 'actif']);
        }
    });

    $client = Client::query()->create(['type' => 'entreprise', 'raison_sociale' => 'Client', 'statut' => 'actif']);
    $this->creerPoste = function (string $nom) use ($client) {
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
    $this->poste = ($this->creerPoste)('Mien');

    $this->ligne = fn (Agent $a, string $date) => [
        'agent_id' => $a->id,
        'site_id' => $this->poste->site_id,
        'poste_id' => $this->poste->id,
        'date_debut' => $date,
        'date_fin' => $date,
        'heure_debut' => '06:00',
        'heure_fin' => '18:00',
    ];
    $this->trou = fn (Poste $poste, string $date) => Vacation::query()->create([
        'agent_id' => ($this->agent)('Absent'.$poste->site_id.$date)->id,
        'site_id' => $poste->site_id,
        'poste_id' => $poste->id,
        'date_debut' => $date,
        'date_fin' => Carbon::parse($date)->addDay()->toDateString(),
        'heure_debut' => '18:00',
        'heure_fin' => '06:00',
        'statut' => 'a_recouvrir',
    ]);
    $this->controleur = function () {
        $user = User::factory()->create();
        $user->assignRole('controleur');
        $fiche = ($this->agent)('Controleur', ['type' => TypeAgent::Controleur, 'user_id' => $user->id]);
        RondierPerimetre::query()->create(['agent_id' => $fiche->id, 'site_id' => $this->poste->site_id]);

        return $user;
    };
});

afterEach(function () {
    Carbon::setTestNow();
});

it('traduit la validation sans nom de champ technique', function () {
    $erreurs = $this->actingAs($this->admin)->postJson('/api/v1/vacations/bulk', [
        'vacations' => [[
            'agent_id' => 'x',
            'poste_id' => '00000000-0000-4000-8000-000000000000',
            'date_debut' => 'abc',
            'heure_debut' => '25h',
            'heure_fin' => '18:00',
        ]],
    ])->assertStatus(422)->json('errors');

    expect($erreurs['vacations.0.agent_id'][0])->toBe('La valeur choisie pour agent n’est pas valide.')
        ->and($erreurs['vacations.0.site_id'][0])->toBe('Le champ site est obligatoire.')
        ->and($erreurs['vacations.0.poste_id'][0])->toBe('La valeur choisie pour poste n’existe pas ou n’est plus disponible.')
        ->and($erreurs['vacations.0.date_debut'][0])->toBe('Le champ date de début doit être une date valide.')
        ->and($erreurs['vacations.0.heure_debut'][0])->toBe('Le champ heure de début n’a pas le bon format.');
});

it('traduit les erreurs de session, de droit et d’élément introuvable', function () {
    $this->getJson('/api/v1/vacations')
        ->assertUnauthorized()->assertJsonPath('message', 'Votre session a expiré. Reconnectez-vous.');

    $this->actingAs(User::factory()->create())->getJson('/api/v1/vacations')
        ->assertForbidden()->assertJsonPath('message', 'Vous n’avez pas le droit d’effectuer cette action.');

    $this->actingAs($this->admin)->getJson('/api/v1/vacations/00000000-0000-4000-8000-000000000000')
        ->assertNotFound()->assertJsonPath('message', 'Élément introuvable : il a peut-être été supprimé. Actualisez la page.');
});

it('ne montre pas le détail d’une panne serveur', function () {
    config(['app.debug' => false]);
    Route::get('api/v1/test-panne', fn () => throw new RuntimeException('SQLSTATE secret'));

    $this->getJson('/api/v1/test-panne')
        ->assertStatus(500)
        ->assertJsonPath('message', 'Une erreur est survenue de notre côté. Réessayez dans un instant.');
});

it('nomme l’agent et le jour dans les refus de planification', function () {
    $sansContrat = ($this->agent)('SansContrat', [], false);
    $this->actingAs($this->admin)->postJson('/api/v1/vacations/bulk', ['vacations' => [($this->ligne)($sansContrat, '2026-11-20')]])
        ->assertStatus(422)
        ->assertJsonPath('errors.agent_id.0', 'Affectation impossible pour Awa SansContrat (M-SansContrat) : pas de contrat valide le 20/11/2026.');

    $enConge = ($this->agent)('Conge', ['statut' => 'conge']);
    $this->postJson('/api/v1/vacations/bulk', ['vacations' => [($this->ligne)($enConge, '2026-11-20')]])
        ->assertStatus(422)
        ->assertJsonPath('errors.agent_id.0', 'Affectation impossible pour Awa Conge (M-Conge) : agent en congé.');

    $pris = ($this->agent)('Pris');
    $this->postJson('/api/v1/vacations/bulk', ['vacations' => [($this->ligne)($pris, '2026-11-20')]])->assertCreated();
    $this->postJson('/api/v1/vacations/bulk', ['vacations' => [($this->ligne)($pris, '2026-11-20')]])
        ->assertStatus(409)
        ->assertJsonPath('message', 'Awa Pris (M-Pris) a déjà une vacation sur ce créneau le 20/11/2026 (06:00–18:00, Mien).');

    $this->postJson('/api/v1/vacations/bulk', ['vacations' => [($this->ligne)(($this->agent)('Second'), '2026-11-20')]])
        ->assertStatus(422)
        ->assertJsonPath('errors.poste_id.0', 'Créneau du 20/11/2026 déjà couvert sur le poste « Entrée » (06:00–18:00) par Awa Pris (M-Pris) : capacité 1 atteinte. Choisissez l’autre quart, un autre jour, ou remplacez l’agent déjà planifié.');
});

it('explique au contrôleur pourquoi il ne peut pas recouvrir', function () {
    $user = ($this->controleur)();
    $libre = ($this->agent)('Libre');

    $this->actingAs($user)->postJson('/api/v1/vacations/'.($this->trou)($this->poste, '2026-11-12')->id.'/recouvrir', ['agent_id' => $libre->id])
        ->assertForbidden()
        ->assertJsonPath('message', 'Sur le terrain, seules les cases d’aujourd’hui peuvent être recouvertes. Pour un autre jour, contactez le planning.');

    $this->postJson('/api/v1/vacations/'.($this->trou)(($this->creerPoste)('Autre'), '2026-11-10')->id.'/recouvrir', ['agent_id' => $libre->id])
        ->assertForbidden()
        ->assertJsonPath('message', 'Ce site n’est pas dans votre périmètre. Contactez le planning.');

    $sansFiche = User::factory()->create();
    $sansFiche->assignRole('controleur');
    $this->actingAs($sansFiche)->getJson('/api/v1/vacations/'.($this->trou)($this->poste, '2026-11-10')->id.'/remplacants')
        ->assertForbidden()
        ->assertJsonPath('message', 'Votre compte n’est lié à aucune fiche agent. Contactez l’administrateur.');
});

it('prévient le second contrôleur que la case vient d’être recouverte', function () {
    $user = ($this->controleur)();
    $trou = ($this->trou)($this->poste, '2026-11-10');

    $this->actingAs($user)->postJson("/api/v1/vacations/{$trou->id}/recouvrir", ['agent_id' => ($this->agent)('Premier')->id])
        ->assertCreated();

    $this->postJson("/api/v1/vacations/{$trou->id}/recouvrir", ['agent_id' => ($this->agent)('Second')->id])
        ->assertStatus(422);
    $this->actingAs($this->admin)->postJson("/api/v1/vacations/{$trou->id}/recouvrir", ['agent_id' => ($this->agent)('Troisieme')->id])
        ->assertStatus(422)
        ->assertJsonPath('errors.vacation.0', 'Cette case vient déjà d’être recouverte. Actualisez la liste.');
});
