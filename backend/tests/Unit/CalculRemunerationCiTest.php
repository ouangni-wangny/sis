<?php

use App\Domain\Paie\CalculRemunerationCi;
use App\Domain\Shared\Enums\SituationMatrimoniale;

it('sums primes into salaire brut', function () {
    $brut = CalculRemunerationCi::salaireBrut(200000, 50000, 25000, 30000, 10000, 15000);
    expect($brut)->toBe(330000.0);
});

it('computes family parts for IGR', function () {
    expect(CalculRemunerationCi::partsFiscales(SituationMatrimoniale::Celibataire, 0))->toBe(1.0);
    expect(CalculRemunerationCi::partsFiscales(SituationMatrimoniale::Marie, 0))->toBe(2.0);
    expect(CalculRemunerationCi::partsFiscales(SituationMatrimoniale::Marie, 2))->toBe(3.0);
    expect(CalculRemunerationCi::partsFiscales(SituationMatrimoniale::Celibataire, 10))->toBe(5.0);
});

it('computes net from brut cnps and igr', function () {
    $result = CalculRemunerationCi::calculer([
        'salaire_base' => 300000,
        'indemnite_fonction' => 50000,
        'prime_transport' => 30000,
        'nombre_enfants' => 2,
        'situation_matrimoniale' => SituationMatrimoniale::Marie,
    ]);

    expect($result['salaire_brut'])->toBe(380000.0);
    expect($result['parts_igr'])->toBe(3.0);
    expect($result['retenue_cnps'])->toBeGreaterThan(0);
    expect($result['salaire_net'])->toBe(
        round($result['salaire_brut'] - $result['retenue_cnps'] - $result['montant_igr'], 2)
    );
    expect($result['salaire_net'])->toBeLessThan($result['salaire_brut']);
});
