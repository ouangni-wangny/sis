<?php

namespace App\Application\Site;

use App\Models\Site;
use Illuminate\Support\Facades\DB;

final class CreateSiteWithPostesAction
{
    public function execute(array $data, array $postes = []): Site
    {
        return DB::transaction(function () use ($data, $postes) {
            $site = Site::query()->create($data);

            foreach ($postes as $poste) {
                $site->postes()->create($poste);
            }

            return $site->load(['postes', 'client', 'zone']);
        });
    }
}
