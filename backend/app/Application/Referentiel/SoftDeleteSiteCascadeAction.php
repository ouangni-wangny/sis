<?php

namespace App\Application\Referentiel;

use App\Models\Checkpoint;
use App\Models\Poste;
use App\Models\Site;
use Illuminate\Support\Facades\DB;

final class SoftDeleteSiteCascadeAction
{
    public function execute(Site $site): void
    {
        DB::transaction(function () use ($site) {
            Poste::query()
                ->where('site_id', $site->id)
                ->each(fn (Poste $poste) => $poste->delete());

            Checkpoint::query()
                ->where('site_id', $site->id)
                ->each(fn (Checkpoint $checkpoint) => $checkpoint->delete());

            $site->delete();
        });
    }
}
