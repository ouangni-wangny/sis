<?php

use App\Application\Operation\DecouperPeriodeVacation;
use App\Domain\Shared\Enums\JourSemaine;

$base = ['agent_id' => 'a', 'site_id' => 's', 'heure_debut' => '07:00', 'heure_fin' => '19:00'];

it('saute le jour de repos dans une période', function () use ($base) {
    $rows = DecouperPeriodeVacation::rows(
        [...$base, 'date_debut' => '2026-11-02', 'date_fin' => '2026-11-15'],
        JourSemaine::Dimanche,
    );

    expect(array_column($rows, 'date_debut'))
        ->toHaveCount(12)
        ->not->toContain('2026-11-08')
        ->not->toContain('2026-11-15');
});

it('garde tous les jours sans jour de repos', function () use ($base) {
    $rows = DecouperPeriodeVacation::rows(
        [...$base, 'date_debut' => '2026-11-02', 'date_fin' => '2026-11-08'],
    );

    expect($rows)->toHaveCount(7);
});

it('ne filtre pas une saisie d’un seul jour', function () use ($base) {
    $rows = DecouperPeriodeVacation::rows(
        [...$base, 'date_debut' => '2026-11-08', 'date_fin' => '2026-11-08'],
        JourSemaine::Dimanche,
    );

    expect($rows)->toHaveCount(1);
});
