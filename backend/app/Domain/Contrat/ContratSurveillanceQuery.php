<?php

namespace App\Domain\Contrat;

use App\Models\Contrat;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class ContratSurveillanceQuery
{
    public const JOURS_ESSAI = 14;

    public const JOURS_FIN = 30;

    /** @param  Builder<Contrat>  $query */
    public static function apply(
        Builder $query,
        int $joursEssai = self::JOURS_ESSAI,
        int $joursFin = self::JOURS_FIN,
        Carbon|string|null $date = null,
    ): Builder {
        $today = $date instanceof Carbon
            ? $date->copy()->startOfDay()
            : Carbon::parse($date ?? now())->startOfDay();

        $todayStr = $today->toDateString();
        $finEssaiMax = $today->copy()->addDays($joursEssai)->toDateString();
        $finContratMax = $today->copy()->addDays($joursFin)->toDateString();
        $finEssaiSql = self::finEssaiSql();

        return $query
            ->where('statut', 'actif')
            ->where(function ($q) use ($todayStr, $finEssaiMax, $finContratMax, $finEssaiSql) {
                $q->where(function ($qq) use ($todayStr, $finEssaiMax, $finEssaiSql) {
                    $qq->where('periode_essai_mois', '>', 0)
                        ->whereRaw("{$finEssaiSql} >= ?", [$todayStr])
                        ->whereRaw("{$finEssaiSql} <= ?", [$finEssaiMax]);
                })->orWhere(function ($qq) use ($todayStr, $finContratMax) {
                    $qq->whereNotNull('date_fin')
                        ->whereDate('date_fin', '>=', $todayStr)
                        ->whereDate('date_fin', '<=', $finContratMax);
                });
            });
    }

    private static function finEssaiSql(): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "date(date_debut, '+' || periode_essai_mois || ' months')",
            default => 'DATE_ADD(date_debut, INTERVAL periode_essai_mois MONTH)',
        };
    }
}
