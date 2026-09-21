<?php

namespace App\Application\Referentiel;

use App\Models\Client;
use Illuminate\Support\Facades\DB;

final class UpdateClientAction
{
    public function execute(Client $client, array $data): Client
    {
        return DB::transaction(function () use ($client, $data) {
            $client->update($data);

            return $client->refresh();
        });
    }
}
