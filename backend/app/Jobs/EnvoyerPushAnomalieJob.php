<?php

namespace App\Jobs;

use App\Models\Anomalie;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class EnvoyerPushAnomalieJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $anomalieId) {}

    public function handle(): void
    {
        $anomalie = Anomalie::query()->with('site')->find($this->anomalieId);
        if (! $anomalie) {
            return;
        }

        // Stub FCM — log for Phase 1
        Log::info('Push anomalie', [
            'anomalie_id' => $anomalie->id,
            'gravite' => $anomalie->gravite,
            'site' => $anomalie->site?->nom,
        ]);
    }
}
