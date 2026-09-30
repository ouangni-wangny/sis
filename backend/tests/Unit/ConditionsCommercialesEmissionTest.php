<?php

use App\Domain\Commercial\ConditionsCommerciales;
use App\Domain\Shared\Enums\PeriodiciteFacturation;

it('pose la date d’émission au dernier jour de la période facturée', function (string $periodeFin, string $expected) {
    expect(ConditionsCommerciales::dateEmissionPourPeriode($periodeFin))->toBe($expected);
})->with([
    ['2026-01-31', '2026-01-31'],
    ['2026-02-28', '2026-02-28'],
    ['2026-09-30', '2026-09-30'],
]);

it('calcule une période mensuelle et sa date d’émission métier', function () {
    [$debut, $fin] = ConditionsCommerciales::periodeFacturee(
        '2026-01-01',
        PeriodiciteFacturation::Mensuel,
    );

    expect($debut)->toBe('2026-01-01')
        ->and($fin)->toBe('2026-01-31')
        ->and(ConditionsCommerciales::dateEmissionPourPeriode($fin))->toBe('2026-01-31');
});
