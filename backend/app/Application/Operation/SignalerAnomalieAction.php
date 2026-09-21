<?php

namespace App\Application\Operation;

use App\Domain\Shared\Enums\GraviteAnomalie;
use App\Jobs\EnvoyerPushAnomalieJob;
use App\Models\Anomalie;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

final class SignalerAnomalieAction
{
    public function execute(array $data, array $photos = []): Anomalie
    {
        return DB::transaction(function () use ($data, $photos) {
            if (! empty($data['client_uuid'])) {
                $existing = Anomalie::query()->where('client_uuid', $data['client_uuid'])->first();
                if ($existing) {
                    return $existing->load('media');
                }
            }

            $data['signale_at'] = $data['signale_at'] ?? now();
            $data['statut'] = $data['statut'] ?? 'ouverte';
            $anomalie = Anomalie::query()->create($data);

            foreach ($photos as $photo) {
                if ($photo instanceof UploadedFile) {
                    $anomalie->addMedia($photo)->toMediaCollection('photos');
                }
            }

            $gravite = $anomalie->gravite;
            if (in_array($gravite, [GraviteAnomalie::Haute, GraviteAnomalie::Critique], true)) {
                EnvoyerPushAnomalieJob::dispatch($anomalie->id);
            }

            return $anomalie->load(['media', 'site', 'signalePar']);
        });
    }
}
