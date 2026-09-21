<?php

namespace App\Jobs;

use App\Application\Contrat\HandleContratClotureAction;
use App\Models\Contrat;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class CloturerContratsExpiresJob implements ShouldQueue
{
    use Queueable;

    public function handle(HandleContratClotureAction $cloture): void
    {
        $today = now()->toDateString();

        $expires = Contrat::query()
            ->where('statut', 'actif')
            ->whereNotNull('date_fin')
            ->whereDate('date_fin', '<', $today)
            ->get();

        $count = 0;

        foreach ($expires as $contrat) {
            $previousStatut = $contrat->statut;
            $contrat->update(['statut' => 'termine']);
            $cloture->execute($contrat->fresh(), $previousStatut);
            $count++;
        }

        if ($count > 0) {
            Log::info('Contrats expirés clôturés automatiquement', ['count' => $count]);
        }
    }
}
