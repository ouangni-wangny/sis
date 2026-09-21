<?php

namespace App\Application\Zone;

use App\Domain\Contrat\ContratValideQuery;
use App\Domain\Shared\Enums\StatutAgent;
use App\Domain\Shared\Enums\TypeAgent;
use App\Domain\Shared\Support\ControleurReleve48h;
use App\Models\Agent;
use App\Models\RondierPerimetre;
use App\Models\Zone;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SyncZoneControleursAction
{
    /**
     * Assigne jusqu’à 2 contrôleurs à une zone (relève 48h).
     *
     * @param  list<string>  $agentIds
     */
    public function execute(Zone $zone, array $agentIds): Zone
    {
        $agentIds = array_values(array_unique(array_filter($agentIds)));

        if (count($agentIds) > ControleurReleve48h::MAX_PAR_ZONE) {
            throw ValidationException::withMessages([
                'agent_ids' => 'Relève 48h : maximum '.ControleurReleve48h::MAX_PAR_ZONE.' contrôleurs par zone.',
            ]);
        }

        return DB::transaction(function () use ($zone, $agentIds) {
            $agents = Agent::query()
                ->whereIn('id', $agentIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($agentIds as $id) {
                $agent = $agents->get($id);
                if (! $agent) {
                    throw ValidationException::withMessages([
                        'agent_ids' => "Agent introuvable ({$id}).",
                    ]);
                }
                if ($agent->type !== TypeAgent::Controleur) {
                    throw ValidationException::withMessages([
                        'agent_ids' => trim($agent->prenom.' '.$agent->nom).' n’est pas un contrôleur.',
                    ]);
                }
                if (in_array($agent->statut, [StatutAgent::Archive, StatutAgent::Suspendu], true)) {
                    throw ValidationException::withMessages([
                        'agent_ids' => trim($agent->prenom.' '.$agent->nom).' est '.$agent->statut->value.' — non assignable.',
                    ]);
                }
                if (! ContratValideQuery::agentHasValidContract($agent->id)) {
                    throw ValidationException::withMessages([
                        'agent_ids' => trim($agent->prenom.' '.$agent->nom).' n’a pas de contrat valide.',
                    ]);
                }
            }

            $existants = RondierPerimetre::query()
                ->where('zone_id', $zone->id)
                ->lockForUpdate()
                ->get();

            $depuis = $existants
                ->first(fn (RondierPerimetre $p) => $p->releve_depuis !== null)
                ?->releve_depuis
                ?->format('Y-m-d')
                ?? now()->toDateString();

            // Retirer les contrôleurs plus sur cette zone.
            foreach ($existants as $perimetre) {
                if (in_array($perimetre->agent_id, $agentIds, true)) {
                    continue;
                }
                $agentId = $perimetre->agent_id;
                $perimetre->delete();
                $this->refreshStatutSiSansPerimetre($agentId);
            }

            // Un contrôleur = une zone : retirer les autres affectations.
            if ($agentIds !== []) {
                $autres = RondierPerimetre::query()
                    ->whereIn('agent_id', $agentIds)
                    ->where('zone_id', '!=', $zone->id)
                    ->get();

                foreach ($autres as $perimetre) {
                    $agentId = $perimetre->agent_id;
                    $perimetre->delete();
                    $this->refreshStatutSiSansPerimetre($agentId);
                }
            }

            $binome = count($agentIds) === ControleurReleve48h::MAX_PAR_ZONE;

            foreach ($agentIds as $index => $agentId) {
                RondierPerimetre::query()->updateOrCreate(
                    [
                        'agent_id' => $agentId,
                        'zone_id' => $zone->id,
                    ],
                    [
                        'indice_releve' => $binome ? $index : null,
                        'releve_depuis' => $binome ? $depuis : null,
                        'releve_jusque' => $binome
                            ? ($existants
                                ->first(fn (RondierPerimetre $p) => $p->releve_jusque !== null)
                                ?->releve_jusque
                                ?->format('Y-m-d'))
                            : null,
                    ],
                );

                $agent = $agents->get($agentId);
                if (
                    $agent
                    && in_array($agent->statut, [StatutAgent::Disponible, StatutAgent::EnActivite], true)
                ) {
                    $agent->update(['statut' => StatutAgent::EnActivite]);
                }
            }

            return $zone->fresh()->load(['sites', 'controleurs']);
        });
    }

    private function refreshStatutSiSansPerimetre(string $agentId): void
    {
        $agent = Agent::query()->find($agentId);
        if (! $agent || $agent->type !== TypeAgent::Controleur) {
            return;
        }
        if ($agent->perimetres()->exists()) {
            return;
        }
        if (in_array($agent->statut, [StatutAgent::Disponible, StatutAgent::EnActivite], true)) {
            $agent->update(['statut' => StatutAgent::Disponible]);
        }
    }
}
