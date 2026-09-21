<?php

namespace App\Application\Agent;

use App\Models\Agent;
use RuntimeException;

/**
 * Matricule séquentiel : 0000A → 9999A → 0000B → … → 9999Z
 */
final class GenerateAgentMatriculeAction
{
    public function execute(): string
    {
        $existing = Agent::withTrashed()->pluck('matricule');

        $maxLetter = null;
        $maxNum = -1;

        foreach ($existing as $matricule) {
            if (preg_match('/^(\d{4})([A-Z])$/', (string) $matricule, $matches) !== 1) {
                continue;
            }

            $num = (int) $matches[1];
            $letter = $matches[2];

            if (
                $maxLetter === null
                || $letter > $maxLetter
                || ($letter === $maxLetter && $num > $maxNum)
            ) {
                $maxLetter = $letter;
                $maxNum = $num;
            }
        }

        if ($maxLetter === null) {
            return '0000A';
        }

        if ($maxNum >= 9999) {
            if ($maxLetter === 'Z') {
                throw new RuntimeException('Capacité de matricules atteinte (9999Z).');
            }

            return '0000'.chr(ord($maxLetter) + 1);
        }

        return sprintf('%04d%s', $maxNum + 1, $maxLetter);
    }
}
