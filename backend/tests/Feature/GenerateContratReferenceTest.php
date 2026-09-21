<?php

use App\Application\Contrat\GenerateContratReferenceAction;
use App\Domain\Shared\Enums\TypeAgent;
use App\Domain\Shared\Enums\TypeContrat;
use App\Models\Agent;
use App\Models\Contrat;
use App\Models\Grade;

it('generates sequential contrat references by type and year', function () {
    $grade = Grade::query()->create([
        'libelle' => 'Agent',
        'type_agent' => TypeAgent::Agent,
    ]);
    $agent = Agent::query()->create([
        'grade_id' => $grade->id,
        'type' => TypeAgent::Agent,
        'nom' => 'Test',
        'prenom' => 'Ref',
        'matricule' => '0000A',
    ]);

    $action = app(GenerateContratReferenceAction::class);

    expect($action->execute(TypeContrat::Cdi, '2026'))->toBe('CTR-CDI-2026-0001');

    Contrat::query()->create([
        'agent_id' => $agent->id,
        'type' => TypeContrat::Cdi,
        'reference' => 'CTR-CDI-2026-0001',
        'date_debut' => '2026-01-01',
        'statut' => 'actif',
    ]);

    expect($action->execute(TypeContrat::Cdi, '2026'))->toBe('CTR-CDI-2026-0002');
    expect($action->execute(TypeContrat::Cdd, '2026'))->toBe('CTR-CDD-2026-0001');
    expect($action->execute(TypeContrat::Stage, '2026'))->toBe('CTR-STG-2026-0001');
});

it('continues sequence after legacy slash references', function () {
    $grade = Grade::query()->create([
        'libelle' => 'Agent Legacy',
        'type_agent' => TypeAgent::Agent,
    ]);
    $agent = Agent::query()->create([
        'grade_id' => $grade->id,
        'type' => TypeAgent::Agent,
        'nom' => 'Legacy',
        'prenom' => 'Ref',
        'matricule' => '0001A',
    ]);

    Contrat::query()->create([
        'agent_id' => $agent->id,
        'type' => TypeContrat::Cdi,
        'reference' => 'CTR/CDI/2026/0001',
        'date_debut' => '2026-01-01',
        'statut' => 'actif',
    ]);

    expect(app(GenerateContratReferenceAction::class)->execute(TypeContrat::Cdi, '2026'))
        ->toBe('CTR-CDI-2026-0002');
});
