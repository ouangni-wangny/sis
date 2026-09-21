<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Operation\EnregistrerControleAction;
use App\Application\Operation\ScannerCheckpointAction;
use App\Application\Operation\SignalerAnomalieAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\MobileSyncRequest;
use App\Http\Resources\AnomalieResource;
use App\Http\Resources\ControleResource;
use App\Http\Resources\RondeResource;
use App\Models\Ronde;
use Illuminate\Http\JsonResponse;

class MobileSyncController extends Controller
{
    public function sync(
        MobileSyncRequest $request,
        EnregistrerControleAction $controleAction,
        SignalerAnomalieAction $anomalieAction,
        ScannerCheckpointAction $scannerAction,
    ): JsonResponse {
        $agent = $request->user()?->agent;
        abort_unless($agent, 403, 'Aucun agent lié à cet utilisateur.');

        $data = $request->validated();
        $controles = [];
        $anomalies = [];
        $rondeScans = [];

        foreach ($data['controles'] ?? [] as $item) {
            $item['agent_id'] = $agent->id;
            $controles[] = new ControleResource($controleAction->execute($item, [], requirePhoto: false));
        }

        foreach ($data['anomalies'] ?? [] as $item) {
            $item['signale_par_id'] = $agent->id;
            $anomalies[] = new AnomalieResource($anomalieAction->execute($item));
        }

        foreach ($data['ronde_scans'] ?? [] as $item) {
            $ronde = Ronde::query()->find($item['ronde_id']);
            abort_unless($ronde && $ronde->agent_id === $agent->id, 403, 'Ronde non liée à cet agent.');

            $rondeScans[] = new RondeResource($scannerAction->execute(
                $ronde,
                $item['checkpoint_id'],
                (float) $item['latitude'],
                (float) $item['longitude'],
                $item['code_qr'],
            ));
        }

        return response()->json([
            'data' => [
                'controles' => $controles,
                'anomalies' => $anomalies,
                'ronde_scans' => $rondeScans,
            ],
        ]);
    }
}
