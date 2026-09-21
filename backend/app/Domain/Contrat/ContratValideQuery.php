<?php

namespace App\Domain\Contrat;

use App\Models\Contrat;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

final class ContratValideQuery
{
    /** @param  Builder<Contrat>  $query */
    public static function apply(Builder $query, Carbon|string|null $date = null): Builder
    {
        $dateStr = $date instanceof Carbon ? $date->toDateString() : ($date ?? now()->toDateString());

        return $query
            ->where('statut', 'actif')
            ->where(function ($q) use ($dateStr) {
                $q->whereNull('date_fin')
                    ->orWhereDate('date_fin', '>=', $dateStr);
            });
    }

    public static function agentHasValidContract(string $agentId, Carbon|string|null $date = null): bool
    {
        return static::apply(
            Contrat::query()->where('agent_id', $agentId),
            $date,
        )->exists();
    }
}
