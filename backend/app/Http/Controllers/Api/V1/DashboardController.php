<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Reporting\GetDashboardStatsAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function stats(Request $request, GetDashboardStatsAction $action): JsonResponse
    {
        abort_unless($request->user()?->can('dashboard.view'), 403);

        $stats = $action->execute(
            $request->input('from'),
            $request->input('to'),
            $request->input('zone_id'),
            $request->input('client_id'),
        );

        return response()->json(['data' => $stats]);
    }
}