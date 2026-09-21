<?php

namespace App\Application\Operation;

use App\Domain\Shared\Enums\StatutRonde;
use App\Domain\Shared\Exceptions\DomainException;
use App\Models\Ronde;
use Illuminate\Support\Facades\DB;

final class TerminerRondeAction
{
    public function execute(Ronde $ronde): Ronde
    {
        return DB::transaction(function () use ($ronde) {
            if ($ronde->statut !== StatutRonde::EnCours) {
                throw new DomainException('Seule une ronde en cours peut être terminée.');
            }

            $total = $ronde->rondeCheckpoints()->count();
            $valides = $ronde->rondeCheckpoints()->where('valide', true)->count();
            $scanned = $ronde->rondeCheckpoints()->whereNotNull('scanne_at')->count();
            $reference = $valides > 0 ? $valides : $scanned;
            $progression = $total > 0 ? (int) round(($reference / $total) * 100) : $ronde->progression;

            $ronde->update([
                'statut' => StatutRonde::Terminee,
                'terminee_at' => now(),
                'progression' => $progression,
            ]);

            return $ronde->fresh(['rondeCheckpoints.checkpoint', 'agent', 'site']);
        });
    }
}
