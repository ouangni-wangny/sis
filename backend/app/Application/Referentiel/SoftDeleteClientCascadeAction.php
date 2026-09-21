<?php

namespace App\Application\Referentiel;

use App\Models\Abonnement;
use App\Models\Anomalie;
use App\Models\Checkpoint;
use App\Models\Client;
use App\Models\Controle;
use App\Models\Facture;
use App\Models\Poste;
use App\Models\Ronde;
use App\Models\Site;
use App\Models\Vacation;
use Illuminate\Support\Facades\DB;

final class SoftDeleteClientCascadeAction
{
    public function execute(Client $client): void
    {
        DB::transaction(function () use ($client) {
            $siteIds = Site::query()
                ->where('client_id', $client->id)
                ->pluck('id');

            if ($siteIds->isNotEmpty()) {
                Poste::query()->whereIn('site_id', $siteIds)->each(fn (Poste $p) => $p->delete());
                Checkpoint::query()->whereIn('site_id', $siteIds)->each(fn (Checkpoint $c) => $c->delete());
                Vacation::query()->whereIn('site_id', $siteIds)->each(fn (Vacation $v) => $v->delete());
                Ronde::query()->whereIn('site_id', $siteIds)->each(fn (Ronde $r) => $r->delete());
                Controle::query()->whereIn('site_id', $siteIds)->each(fn (Controle $c) => $c->delete());
                Anomalie::query()->whereIn('site_id', $siteIds)->each(fn (Anomalie $a) => $a->delete());

                Site::query()->whereIn('id', $siteIds)->each(fn (Site $s) => $s->delete());
            }

            Abonnement::query()
                ->where('client_id', $client->id)
                ->each(fn (Abonnement $a) => $a->delete());

            Facture::query()
                ->where('client_id', $client->id)
                ->each(fn (Facture $f) => $f->delete());

            $client->delete();
        });
    }
}
