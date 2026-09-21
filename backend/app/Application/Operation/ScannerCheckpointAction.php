<?php

namespace App\Application\Operation;

use App\Domain\Shared\Enums\StatutRonde;
use App\Domain\Shared\Exceptions\DomainException;
use App\Models\Checkpoint;
use App\Models\Ronde;
use App\Support\Geo\Haversine;
use Illuminate\Support\Facades\DB;

final class ScannerCheckpointAction
{
    public function execute(
        Ronde $ronde,
        string $checkpointId,
        ?float $latitude = null,
        ?float $longitude = null,
        ?string $codeQr = null,
    ): Ronde {
        return DB::transaction(function () use ($ronde, $checkpointId, $latitude, $longitude, $codeQr) {
            if ($ronde->statut !== StatutRonde::EnCours) {
                throw new DomainException('La ronde doit être en cours pour scanner.');
            }

            $checkpoint = Checkpoint::query()->with('site')->findOrFail($checkpointId);
            if ($checkpoint->site_id !== $ronde->site_id) {
                throw new DomainException('Checkpoint hors site de la ronde.');
            }

            if ($latitude === null || $longitude === null) {
                throw new DomainException('Position GPS (latitude/longitude) requise pour scanner un checkpoint.');
            }

            if ($checkpoint->code_qr && (! $codeQr || $codeQr !== $checkpoint->code_qr)) {
                throw new DomainException('Code QR invalide.');
            }

            $valide = true;
            if ($checkpoint->latitude && $checkpoint->longitude) {
                $radius = (float) ($checkpoint->site?->rayon_metres ?: 200);
                $valide = Haversine::isWithinRadius(
                    $latitude, $longitude,
                    (float) $checkpoint->latitude, (float) $checkpoint->longitude,
                    $radius
                );
            }

            $rc = $ronde->rondeCheckpoints()->firstOrCreate(['checkpoint_id' => $checkpoint->id]);
            $rc->update([
                'scanne_at' => now(),
                'latitude' => $latitude,
                'longitude' => $longitude,
                'valide' => $valide,
            ]);

            $total = $ronde->rondeCheckpoints()->count();
            $scanned = $ronde->rondeCheckpoints()->whereNotNull('scanne_at')->count();
            $progression = $total > 0 ? (int) round(($scanned / $total) * 100) : 0;
            $ronde->update(['progression' => $progression]);

            return $ronde->fresh(['rondeCheckpoints.checkpoint']);
        });
    }
}
