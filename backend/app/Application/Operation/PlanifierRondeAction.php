<?php

namespace App\Application\Operation;

use App\Domain\Shared\Enums\StatutAgent;
use App\Domain\Shared\Enums\StatutRonde;
use App\Domain\Shared\Enums\StatutVacation;
use App\Domain\Shared\Enums\TypeAgent;
use App\Models\Agent;
use App\Models\Ronde;
use App\Models\RondierPerimetre;
use App\Models\Site;
use App\Models\Vacation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PlanifierRondeAction
{
    /**
     * @param  array{agent_id: string, site_id: string, vacation_id?: string|null}  $data
     */
    public function execute(array $data): Ronde
    {
        return DB::transaction(function () use ($data) {
            $agent = Agent::query()->findOrFail($data['agent_id']);
            $site = Site::query()->findOrFail($data['site_id']);

            $this->assertAgentEligibile($agent);
            $this->assertSiteHasCheckpoints($site);
            $this->assertPerimetre($agent, $site);
            $this->assertPasDeRondeOuverte($agent->id);

            if (! empty($data['vacation_id'])) {
                $this->assertVacationCompatible($data['vacation_id'], $agent->id, $site->id);
            } else {
                $data['vacation_id'] = null;
            }

            $data['statut'] = StatutRonde::Planifiee->value;
            $data['progression'] = 0;

            return Ronde::query()->create($data)->load(['agent', 'site']);
        });
    }

    private function assertAgentEligibile(Agent $agent): void
    {
        if ($agent->type !== TypeAgent::Controleur) {
            throw ValidationException::withMessages([
                'agent_id' => 'Seul un agent de type contrôleur peut être assigné à une ronde.',
            ]);
        }

        $bloque = [
            StatutAgent::Suspendu,
            StatutAgent::Archive,
            StatutAgent::Conge,
            StatutAgent::Malade,
        ];

        if (in_array($agent->statut, $bloque, true)) {
            throw ValidationException::withMessages([
                'agent_id' => 'Ce contrôleur n’est pas disponible (statut : '.$agent->statut->value.').',
            ]);
        }
    }

    private function assertSiteHasCheckpoints(Site $site): void
    {
        if ($site->checkpoints()->count() < 1) {
            throw ValidationException::withMessages([
                'site_id' => 'Ce site n’a aucun checkpoint. Ajoutez-en avant de planifier une ronde.',
            ]);
        }
    }

    private function assertPerimetre(Agent $agent, Site $site): void
    {
        $hasPerimetres = RondierPerimetre::query()
            ->where('agent_id', $agent->id)
            ->exists();

        if (! $hasPerimetres) {
            return;
        }

        $autorise = RondierPerimetre::query()
            ->where('agent_id', $agent->id)
            ->where(function ($q) use ($site) {
                $q->where('site_id', $site->id)
                    ->orWhere(function ($inner) use ($site) {
                        $inner->whereNull('site_id')
                            ->where('zone_id', $site->zone_id);
                    });
            })
            ->exists();

        if (! $autorise) {
            throw ValidationException::withMessages([
                'site_id' => 'Ce site n’est pas dans le périmètre autorisé de ce contrôleur.',
            ]);
        }
    }

    private function assertPasDeRondeOuverte(string $agentId): void
    {
        $ouverte = Ronde::query()
            ->where('agent_id', $agentId)
            ->whereIn('statut', [StatutRonde::Planifiee, StatutRonde::EnCours])
            ->exists();

        if ($ouverte) {
            throw ValidationException::withMessages([
                'agent_id' => 'Ce contrôleur a déjà une ronde planifiée ou en cours. Terminez-la avant d’en planifier une nouvelle.',
            ]);
        }
    }

    private function assertVacationCompatible(string $vacationId, string $agentId, string $siteId): void
    {
        $vacation = Vacation::query()->findOrFail($vacationId);

        if ($vacation->agent_id !== $agentId) {
            throw ValidationException::withMessages([
                'vacation_id' => 'La vacation sélectionnée n’appartient pas à ce contrôleur.',
            ]);
        }

        if ($vacation->site_id !== $siteId) {
            throw ValidationException::withMessages([
                'vacation_id' => 'La vacation sélectionnée n’est pas sur ce site.',
            ]);
        }

        $invalides = [StatutVacation::Annulee, StatutVacation::Terminee];
        if (in_array($vacation->statut, $invalides, true)) {
            throw ValidationException::withMessages([
                'vacation_id' => 'Impossible de lier une vacation annulée ou terminée.',
            ]);
        }
    }
}
