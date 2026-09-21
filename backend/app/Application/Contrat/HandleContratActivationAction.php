<?php

namespace App\Application\Contrat;

use App\Domain\Contrat\ContratValideQuery;
use App\Domain\Shared\Enums\StatutAgent;
use App\Models\Agent;
use App\Models\Contrat;

final class HandleContratActivationAction
{
    /**
     * Si un contrat valide (actif + non expiré) existe, un agent archivé
     * redevient disponible — cas typique : nouveau contrat ou réactivation.
     */
    public function execute(Contrat $contrat): void
    {
        if ($contrat->statut !== 'actif') {
            return;
        }

        if (! ContratValideQuery::agentHasValidContract($contrat->agent_id)) {
            return;
        }

        $agent = $contrat->agent ?? Agent::query()->find($contrat->agent_id);
        if (! $agent || $agent->statut !== StatutAgent::Archive) {
            return;
        }

        $agent->update(['statut' => StatutAgent::Disponible]);
    }
}
