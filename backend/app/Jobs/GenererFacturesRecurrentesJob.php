<?php

namespace App\Jobs;

use App\Application\Commercial\GenererFacturesRecurrentesAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class GenererFacturesRecurrentesJob implements ShouldQueue
{
    use Queueable;

    public function handle(GenererFacturesRecurrentesAction $action): void
    {
        $stats = $action->execute();

        Log::info('Factures récurrentes générées', $stats);
    }
}
