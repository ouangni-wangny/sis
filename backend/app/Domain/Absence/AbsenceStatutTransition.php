<?php

namespace App\Domain\Absence;

use App\Domain\Shared\Enums\StatutAbsence;
use Illuminate\Validation\ValidationException;

final class AbsenceStatutTransition
{
    /** @var array<string, list<string>> */
    private const ALLOWED = [
        'en_attente' => ['approuvee', 'refusee', 'annulee'],
        'approuvee' => ['annulee'],
        'refusee' => [],
        'annulee' => [],
    ];

    /** @var list<string> */
    private const CREATABLE = ['en_attente', 'approuvee'];

    public static function assertCreatable(StatutAbsence $statut): void
    {
        if (! in_array($statut->value, self::CREATABLE, true)) {
            throw ValidationException::withMessages([
                'statut' => 'Seuls les statuts « en attente » et « approuvée » sont autorisés à la création.',
            ]);
        }
    }

    public static function assertCanTransition(
        StatutAbsence $from,
        StatutAbsence $to,
    ): void {
        if ($from === $to) {
            return;
        }

        $allowed = self::ALLOWED[$from->value] ?? [];

        if (! in_array($to->value, $allowed, true)) {
            throw ValidationException::withMessages([
                'statut' => sprintf(
                    'Transition impossible de « %s » vers « %s ».',
                    $from->value,
                    $to->value,
                ),
            ]);
        }
    }

    public static function isTerminal(StatutAbsence $statut): bool
    {
        return in_array($statut, [StatutAbsence::Refusee, StatutAbsence::Annulee], true);
    }
}
