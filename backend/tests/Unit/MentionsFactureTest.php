<?php

/*
 * Retour client 5 — quand la proforma devient facture (validée), les mentions
 * proforma disparaissent ; elles restent sur une proforma (en attente).
 */

use App\Domain\Commercial\MentionsFacture;
use App\Domain\Shared\Enums\StatutFacture;

it('garde les mentions proforma tant que la facture est en attente', function () {
    expect(MentionsFacture::pour(StatutFacture::EnAttente))->toBe([
        'definitive' => false,
        'titre' => 'Facture pro forma',
        'arrete' => 'Arrêté le présent devis à la somme de',
        'afficher_nb' => true,
        'afficher_conditions' => true,
    ]);
});

it('retire les mentions proforma sur une facture définitive', function () {
    expect(MentionsFacture::pour(StatutFacture::Valide))->toBe([
        'definitive' => true,
        'titre' => 'Facture',
        'arrete' => 'Arrêté la présente facture à la somme de',
        'afficher_nb' => false,
        'afficher_conditions' => false,
    ]);
});

it('accepte le statut sous forme de chaîne', function () {
    expect(MentionsFacture::pour('valide')['definitive'])->toBeTrue()
        ->and(MentionsFacture::pour(null)['definitive'])->toBeFalse();
});

it('garde les mentions définitives sur une facture annulée après émission', function () {
    expect(MentionsFacture::pour(StatutFacture::Annulee)['definitive'])->toBeTrue()
        ->and(MentionsFacture::pour(StatutFacture::Rejetee)['definitive'])->toBeFalse();
});
