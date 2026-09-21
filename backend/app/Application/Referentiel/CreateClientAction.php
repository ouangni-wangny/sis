<?php

namespace App\Application\Referentiel;

use App\Models\Client;
use Illuminate\Support\Facades\DB;

final class CreateClientAction
{
    public function execute(array $data): Client
    {
        return DB::transaction(fn () => Client::query()->create($data));
    }
}
