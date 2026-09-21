<?php

namespace App\Application\Operation;

use App\Domain\Shared\Enums\StatutAnomalie;
use App\Domain\Shared\Exceptions\TransitionAnomalieInvalideException;
use App\Models\Anomalie;
use Illuminate\Support\Facades\DB;

final class UpdateAnomalieStatutAction
{
    private const TRANSITIONS = [
        'ouverte' => ['en_cours'],
        'en_cours' => ['resolue'],
        'resolue' => [],
    ];

    public function execute(Anomalie $anomalie, string $statut, ?string $assigneAId = null): Anomalie
    {
        return DB::transaction(function () use ($anomalie, $statut, $assigneAId) {
            $from = $anomalie->statut->value;
            $allowed = self::TRANSITIONS[$from] ?? [];

            if (! in_array($statut, $allowed, true)) {
                throw new TransitionAnomalieInvalideException(
                    "Transition invalide : {$from} → {$statut}."
                );
            }

            $payload = ['statut' => $statut];
            if ($assigneAId) {
                $payload['assigne_a_id'] = $assigneAId;
            }
            if ($statut === StatutAnomalie::Resolue->value) {
                $payload['resolue_at'] = now();
            }

            $anomalie->update($payload);

            return $anomalie->fresh(['assigneA', 'site', 'signalePar']);
        });
    }
}
