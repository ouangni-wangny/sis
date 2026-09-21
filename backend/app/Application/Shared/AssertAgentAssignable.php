<?php

namespace App\Application\Shared;

use App\Domain\Contrat\ContratValideQuery;
use App\Domain\Shared\Enums\StatutAgent;
use App\Domain\Shared\Enums\TypeAgent;
use App\Models\Agent;
use App\Models\Poste;
use Illuminate\Validation\ValidationException;

final class AssertAgentAssignable
{
    /** @param  list<StatutAgent>|null  $allowed */
    public static function execute(
        string $agentId,
        ?TypeAgent $requiredType = null,
        ?array $allowedStatuts = null,
        ?string $referenceDate = null,
    ): Agent {
        $agent = Agent::query()->findOrFail($agentId);

        if ($requiredType !== null && $agent->type !== $requiredType) {
            throw ValidationException::withMessages([
                'agent_id' => 'Type d’agent incompatible (attendu : '.$requiredType->value.').',
            ]);
        }

        $allowed = $allowedStatuts ?? [
            StatutAgent::Disponible,
            StatutAgent::EnActivite,
        ];

        if (! in_array($agent->statut, $allowed, true)) {
            throw ValidationException::withMessages([
                'agent_id' => 'Cet agent n’est pas assignable (statut : '.$agent->statut->value.').',
            ]);
        }

        if (! ContratValideQuery::agentHasValidContract($agentId, $referenceDate)) {
            throw ValidationException::withMessages([
                'agent_id' => 'Cet agent n’a pas de contrat valide à cette date.',
            ]);
        }

        return $agent;
    }

    public static function posteBelongsToSite(?string $posteId, string $siteId): void
    {
        if (blank($posteId)) {
            return;
        }

        $ok = Poste::query()
            ->where('id', $posteId)
            ->where('site_id', $siteId)
            ->exists();

        if (! $ok) {
            throw ValidationException::withMessages([
                'poste_id' => 'Ce poste n’appartient pas au site sélectionné.',
            ]);
        }
    }
}
