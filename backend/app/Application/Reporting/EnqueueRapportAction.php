<?php

namespace App\Application\Reporting;

use App\Jobs\GenererRapportJob;
use App\Models\RapportExport;
use App\Models\User;

final class EnqueueRapportAction
{
    public function execute(User $user, string $type, string $format = 'pdf', array $filtres = []): RapportExport
    {
        $rapport = RapportExport::query()->create([
            'user_id' => $user->id,
            'type' => $type,
            'format' => $format,
            'statut' => 'pending',
            'filtres' => $filtres,
        ]);

        // Sync : pas de worker queue requis en local / mono-instance.
        GenererRapportJob::dispatchSync($rapport->id);

        return $rapport->fresh()->load('media');
    }
}
