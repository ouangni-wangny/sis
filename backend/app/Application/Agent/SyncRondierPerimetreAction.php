<?php

namespace App\Application\Agent;

use App\Domain\Contrat\ContratValideQuery;
use App\Domain\Shared\Enums\StatutAgent;
use App\Domain\Shared\Enums\TypeAgent;
use App\Domain\Shared\Support\ControleurReleve48h;
use App\Models\Agent;
use App\Models\RondierPerimetre;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SyncRondierPerimetreAction
{
    /**
     * @param  array<int, array{zone_id?: ?string}>  $items
     */
    public function execute(Agent $agent, array $items): Agent
    {
        if ($items !== [] && $agent->type !== TypeAgent::Controleur) {
            throw ValidationException::withMessages([
                'agent_id' => 'Seuls les agents de type contrôleur peuvent avoir un périmètre.',
            ]);
        }

        if ($items !== [] && ! ContratValideQuery::agentHasValidContract($agent->id)) {
            throw ValidationException::withMessages([
                'agent_id' => 'Cet agent n’a pas de contrat valide.',
            ]);
        }

        return DB::transaction(function () use ($agent, $items) {
            foreach ($items as $item) {
                if (empty($item['zone_id'])) {
                    continue;
                }

                $occupants = RondierPerimetre::query()
                    ->where('zone_id', $item['zone_id'])
                    ->where('agent_id', '!=', $agent->id)
                    ->whereHas('agent', fn ($q) => $q->whereNotIn('statut', [
                        StatutAgent::Archive->value,
                        StatutAgent::Suspendu->value,
                    ]))
                    ->with('agent')
                    ->lockForUpdate()
                    ->get();

                if ($occupants->count() >= ControleurReleve48h::MAX_PAR_ZONE) {
                    $noms = $occupants
                        ->map(fn (RondierPerimetre $p) => trim($p->agent->prenom.' '.$p->agent->nom))
                        ->implode(', ');
                    throw ValidationException::withMessages([
                        'items' => "Cette zone a déjà ".ControleurReleve48h::MAX_PAR_ZONE." contrôleurs ({$noms}). Relève 48h = 2 max.",
                    ]);
                }
            }

            $agent->perimetres()->delete();

            foreach ($items as $item) {
                if (empty($item['zone_id'])) {
                    continue;
                }

                [$indice, $depuis] = $this->resolveReleve((string) $item['zone_id'], $agent->id);

                $agent->perimetres()->create([
                    'zone_id' => $item['zone_id'],
                    'indice_releve' => $indice,
                    'releve_depuis' => $depuis,
                ]);
            }

            if (
                $agent->type === TypeAgent::Controleur
                && in_array($agent->statut, [StatutAgent::Disponible, StatutAgent::EnActivite], true)
            ) {
                $agent->update([
                    'statut' => $items !== [] ? StatutAgent::EnActivite : StatutAgent::Disponible,
                ]);
            }

            return $agent->load(['perimetres.zone']);
        });
    }

    /**
     * @return array{0: int|null, 1: string|null} [indice_releve, releve_depuis Y-m-d]
     */
    private function resolveReleve(string $zoneId, string $agentId): array
    {
        $autres = RondierPerimetre::query()
            ->where('zone_id', $zoneId)
            ->where('agent_id', '!=', $agentId)
            ->whereHas('agent', fn ($q) => $q->whereNotIn('statut', [
                StatutAgent::Archive->value,
                StatutAgent::Suspendu->value,
            ]))
            ->orderBy('created_at')
            ->get();

        if ($autres->isEmpty()) {
            // Seul sur la zone : pas encore de binôme 48h.
            return [null, null];
        }

        $premier = $autres->first();
        $depuis = $premier->releve_depuis?->format('Y-m-d')
            ?? ($premier->created_at?->toDateString() ?? now()->toDateString());

        // Si le premier n’avait pas d’indice (était seul), on le passe en binôme 0.
        if ($premier->indice_releve === null) {
            $premier->update([
                'indice_releve' => 0,
                'releve_depuis' => $depuis,
            ]);
        } else {
            $depuis = $premier->releve_depuis?->format('Y-m-d') ?? $depuis;
        }

        $pris = $autres
            ->map(fn (RondierPerimetre $p) => $p->indice_releve)
            ->filter(fn ($i) => $i !== null)
            ->map(fn ($i) => (int) $i)
            ->unique()
            ->all();

        $indice = in_array(0, $pris, true) ? 1 : 0;

        return [$indice, $depuis];
    }
}
