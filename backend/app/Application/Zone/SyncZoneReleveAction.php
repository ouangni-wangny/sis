<?php

namespace App\Application\Zone;

use App\Domain\Shared\Support\ControleurReleve48h;
use App\Models\RondierPerimetre;
use App\Models\Zone;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SyncZoneReleveAction
{
    /**
     * Définit la période de relève 48h (et éventuellement l’ordre des indices)
     * pour les 2 contrôleurs d’une zone.
     *
     * @param  list<string>|null  $ordreAgentIds  Optionnel : [indice0, indice1]
     */
    public function execute(
        Zone $zone,
        string $releveDepuis,
        string $releveJusque,
        ?array $ordreAgentIds = null,
    ): Zone {
        if ($releveJusque < $releveDepuis) {
            throw ValidationException::withMessages([
                'releve_jusque' => 'La date de fin doit être postérieure ou égale au début.',
            ]);
        }

        return DB::transaction(function () use ($zone, $releveDepuis, $releveJusque, $ordreAgentIds) {
            $perimetres = RondierPerimetre::query()
                ->where('zone_id', $zone->id)
                ->with('agent')
                ->lockForUpdate()
                ->orderBy('indice_releve')
                ->orderBy('created_at')
                ->get();

            if ($perimetres->count() < ControleurReleve48h::MAX_PAR_ZONE) {
                throw ValidationException::withMessages([
                    'zone' => 'Assignez 2 contrôleurs à cette zone (menu Zones) avant de planifier la relève.',
                ]);
            }

            if ($perimetres->count() > ControleurReleve48h::MAX_PAR_ZONE) {
                throw ValidationException::withMessages([
                    'zone' => 'Cette zone a plus de 2 contrôleurs — corrigez l’affectation.',
                ]);
            }

            $ordered = $perimetres;
            if ($ordreAgentIds !== null && $ordreAgentIds !== []) {
                $ordreAgentIds = array_values(array_unique(array_filter($ordreAgentIds)));
                if (count($ordreAgentIds) !== ControleurReleve48h::MAX_PAR_ZONE) {
                    throw ValidationException::withMessages([
                        'ordre_agent_ids' => 'Indiquez exactement les 2 contrôleurs de la zone, dans l’ordre souhaité.',
                    ]);
                }
                $byId = $perimetres->keyBy('agent_id');
                $ordered = collect();
                foreach ($ordreAgentIds as $id) {
                    $p = $byId->get($id);
                    if (! $p) {
                        throw ValidationException::withMessages([
                            'ordre_agent_ids' => 'Un agent indiqué n’est pas affecté à cette zone.',
                        ]);
                    }
                    $ordered->push($p);
                }
            }

            foreach ($ordered->values() as $index => $perimetre) {
                $perimetre->update([
                    'indice_releve' => $index,
                    'releve_depuis' => $releveDepuis,
                    'releve_jusque' => $releveJusque,
                ]);
            }

            return $zone->fresh()->load(['controleurs'])->loadCount(['sites', 'controleurs']);
        });
    }
}
