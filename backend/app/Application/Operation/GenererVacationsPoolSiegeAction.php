<?php

namespace App\Application\Operation;

use App\Domain\Shared\Enums\StatutAgent;
use App\Domain\Shared\Exceptions\DomainException;
use App\Models\Agent;
use App\Models\Vacation;
use Illuminate\Validation\ValidationException;

/**
 * Pointe automatiquement, chaque jour, les agents du pool siège (rattachés
 * en permanence à un poste sur un site interne) via une vacation d'un jour.
 *
 * Volontairement rejouable et non bloquant : un agent déjà engagé ailleurs
 * ce jour-là (ex. déployé en remplacement sur un site client) est
 * simplement sauté — sa présence "siège" ne doit jamais entrer en conflit
 * avec une affectation réelle décidée par Opération.
 */
final class GenererVacationsPoolSiegeAction
{
    private const STATUTS_ELIGIBLES = [
        StatutAgent::Disponible->value,
        StatutAgent::EnActivite->value,
    ];

    public function __construct(
        private readonly CreateVacationAction $create,
    ) {}

    /** @return array{created: int, skipped: int, errors: list<string>} */
    public function execute(?string $date = null): array
    {
        $date = $date ?? now()->toDateString();

        $agents = Agent::query()
            ->where('pool_siege', true)
            ->whereNotNull('poste_siege_id')
            ->whereIn('statut', self::STATUTS_ELIGIBLES)
            ->with('posteSiege')
            ->get();

        $created = 0;
        $skipped = 0;
        $errors = [];

        foreach ($agents as $agent) {
            $poste = $agent->posteSiege;

            if (! $poste || ! $poste->heure_debut || ! $poste->heure_fin) {
                $skipped++;

                continue;
            }

            $alreadyPlanned = Vacation::query()
                ->where('agent_id', $agent->id)
                ->where('poste_id', $poste->id)
                ->whereDate('date_debut', $date)
                ->where('statut', '!=', 'annulee')
                ->exists();

            if ($alreadyPlanned) {
                $skipped++;

                continue;
            }

            try {
                $this->create->execute([
                    'agent_id' => $agent->id,
                    'site_id' => $poste->site_id,
                    'poste_id' => $poste->id,
                    'date_debut' => $date,
                    'date_fin' => $date,
                    'heure_debut' => substr((string) $poste->heure_debut, 0, 5),
                    'heure_fin' => substr((string) $poste->heure_fin, 0, 5),
                ]);
                $created++;
            } catch (DomainException|ValidationException $e) {
                // Agent indisponible ce jour (ex. déjà déployé en remplacement,
                // repos légal, poste saturé) : on saute sans casser le lot.
                $skipped++;
                $errors[] = "{$agent->id}: {$e->getMessage()}";
            }
        }

        return ['created' => $created, 'skipped' => $skipped, 'errors' => $errors];
    }
}
