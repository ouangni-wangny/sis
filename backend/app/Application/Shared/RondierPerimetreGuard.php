<?php

namespace App\Application\Shared;

use App\Domain\Shared\Enums\StatutVacation;
use App\Domain\Shared\Enums\TypeAgent;
use App\Models\Agent;
use App\Models\Site;
use App\Models\Vacation;
use Illuminate\Validation\ValidationException;

/**
 * Un contrôleur ne doit contrôler que les sites relevant de son périmètre assigné
 * (RondierPerimetre : zone_id et/ou site_id explicite).
 */
final class RondierPerimetreGuard
{
    /** @return list<string> */
    public static function authorizedSiteIds(Agent $rondier): array
    {
        $perimetres = $rondier->perimetres()->get(['zone_id', 'site_id']);

        $siteIds = $perimetres->pluck('site_id')->filter()->values();
        $zoneIds = $perimetres->pluck('zone_id')->filter()->unique()->values();

        if ($zoneIds->isNotEmpty()) {
            $siteIds = $siteIds->merge(
                Site::query()->whereIn('zone_id', $zoneIds)->pluck('id'),
            );
        }

        return $siteIds->unique()->values()->all();
    }

    /** Agents postés (type=agent) actuellement en vacation active sur les sites du périmètre. */
    public static function activeAgentIds(Agent $rondier): array
    {
        $siteIds = self::authorizedSiteIds($rondier);
        if ($siteIds === []) {
            return [];
        }

        $today = now()->toDateString();

        return Vacation::query()
            ->whereIn('site_id', $siteIds)
            ->whereIn('statut', [StatutVacation::Planifiee, StatutVacation::EnCours])
            ->whereDate('date_debut', '<=', $today)
            ->where(function ($q) use ($today) {
                $q->whereNull('date_fin')->orWhereDate('date_fin', '>=', $today);
            })
            ->whereHas('agent', fn ($a) => $a->where('type', TypeAgent::Agent))
            ->distinct()
            ->pluck('agent_id')
            ->all();
    }

    public static function assertSiteAllowed(Agent $rondier, string $siteId): void
    {
        if (! in_array($siteId, self::authorizedSiteIds($rondier), true)) {
            throw ValidationException::withMessages([
                'site_id' => 'Ce site ne fait pas partie de votre périmètre assigné.',
            ]);
        }
    }
}
