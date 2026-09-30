<?php

use App\Domain\Shared\Enums\DirectionMouvementTresorerie;

it('crédite le compte pour entrée, retour et approvisionnement', function (DirectionMouvementTresorerie $direction, bool $credite) {
    expect($direction->crediteLeCompte())->toBe($credite);
})->with([
    [DirectionMouvementTresorerie::Entree, true],
    [DirectionMouvementTresorerie::Retour, true],
    [DirectionMouvementTresorerie::Approvisionnement, true],
    [DirectionMouvementTresorerie::Sortie, false],
]);

it('inverse correctement les directions métier', function (DirectionMouvementTresorerie $from, DirectionMouvementTresorerie $to) {
    expect($from->inverse())->toBe($to);
})->with([
    [DirectionMouvementTresorerie::Entree, DirectionMouvementTresorerie::Sortie],
    [DirectionMouvementTresorerie::Sortie, DirectionMouvementTresorerie::Retour],
    [DirectionMouvementTresorerie::Retour, DirectionMouvementTresorerie::Sortie],
    [DirectionMouvementTresorerie::Approvisionnement, DirectionMouvementTresorerie::Sortie],
]);
