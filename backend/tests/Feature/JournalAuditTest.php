<?php

use App\Domain\Shared\Enums\TypeAgent;
use App\Models\Agent;
use App\Models\Contrat;
use App\Models\Grade;
use App\Models\JournalAudit;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->admin = User::factory()->create([
        'email' => 'audit@sis.ci',
        'password' => Hash::make('password'),
    ]);
    $this->admin->assignRole('super-admin');

    $grade = Grade::query()->create([
        'libelle' => 'Agent',
        'type_agent' => TypeAgent::Agent,
    ]);

    $this->agent = Agent::query()->create([
        'grade_id' => $grade->id,
        'type' => TypeAgent::Agent,
        'nom' => 'Audit',
        'prenom' => 'Test',
        'matricule' => 'AUD-001',
        'statut' => 'disponible',
    ]);
});

it('enregistre le détail des champs modifiés sur un contrat', function () {
    $contrat = Contrat::query()->create([
        'agent_id' => $this->agent->id,
        'type' => 'cdd',
        'reference' => 'CTR-AUD-001',
        'date_debut' => '2026-01-01',
        'date_fin' => '2026-06-30',
        'statut' => 'actif',
    ]);

    $this->actingAs($this->admin)->patchJson("/api/v1/contrats/{$contrat->id}", [
        'statut' => 'termine',
        'date_fin' => '2026-06-30',
    ])->assertOk();

    $entry = JournalAudit::query()
        ->where('auditable_type', Contrat::class)
        ->where('auditable_id', $contrat->id)
        ->where('action', 'updated')
        ->latest()
        ->first();

    expect($entry)->not->toBeNull()
        ->and($entry->user_id)->toBe($this->admin->id)
        ->and($entry->nouveau)->toHaveKey('statut')
        ->and($entry->nouveau['statut'])->toBe('termine')
        ->and($entry->ancien['statut'])->toBe('actif')
        ->and($entry->resume)->toContain('Modification')
        ->and($entry->contexte)->toHaveKey('via');
});

it('expose les changements dans le journal d’audit', function () {
    $this->agent->update(['statut' => 'archive']);

    $response = $this->actingAs($this->admin)->getJson('/api/v1/journal-audit?auditable_type=Agent&per_page=10');

    $response->assertOk();
    $row = collect($response->json('data'))->firstWhere('auditable_id', $this->agent->id);

    expect($row)->not->toBeNull()
        ->and($row['changes'])->not->toBeEmpty()
        ->and(collect($row['changes'])->pluck('field'))->toContain('statut')
        ->and($row['action_label'])->toBe('Modification')
        ->and($row['acteur'])->not->toBeEmpty();
});

it('affiche le détail d’un événement d’audit', function () {
    $this->actingAs($this->admin)->putJson("/api/v1/agents/{$this->agent->id}", [
        'statut' => 'archive',
    ])->assertOk();

    $entry = JournalAudit::query()
        ->where('auditable_id', $this->agent->id)
        ->where('action', 'updated')
        ->latest()
        ->firstOrFail();

    $response = $this->actingAs($this->admin)->getJson("/api/v1/journal-audit/{$entry->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $entry->id)
        ->assertJsonPath('data.action_label', 'Modification')
        ->assertJsonPath('data.auditable_type_label', 'Personnel');

    expect($response->json('data.explication'))->toContain('a modifié')
        ->and($response->json('data.acteur'))->not->toBe('Système automatique');
});

it('résout les identifiants en libellés lisibles', function () {
    $autreGrade = Grade::query()->create([
        'libelle' => 'Chef de poste',
        'type_agent' => TypeAgent::Agent,
    ]);

    $this->actingAs($this->admin)->putJson("/api/v1/agents/{$this->agent->id}", [
        'grade_id' => $autreGrade->id,
    ])->assertOk();

    $entry = JournalAudit::query()
        ->where('auditable_id', $this->agent->id)
        ->where('action', 'updated')
        ->latest()
        ->firstOrFail();

    $response = $this->actingAs($this->admin)->getJson("/api/v1/journal-audit/{$entry->id}");
    $change = collect($response->json('data.changes'))->firstWhere('field', 'grade_id');

    expect($change)->not->toBeNull()
        ->and($change['before_label'])->toBe('Agent')
        ->and($change['after_label'])->toBe('Chef de poste')
        ->and($change['after_label'])->not->toBe($autreGrade->id);
});
