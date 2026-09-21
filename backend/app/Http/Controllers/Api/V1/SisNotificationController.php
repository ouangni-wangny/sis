<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SisNotificationResource;
use App\Models\SisNotification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SisNotificationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $items = SisNotification::query()
            ->where('user_id', $request->user()->id)
            ->when($request->boolean('non_lues'), fn ($q) => $q->whereNull('lue_le'))
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return SisNotificationResource::collection($items);
    }

    public function marquerLue(Request $request, SisNotification $sisNotification): SisNotificationResource
    {
        abort_unless($sisNotification->user_id === $request->user()->id, 403);

        $sisNotification->update(['lue_le' => now()]);

        return new SisNotificationResource($sisNotification);
    }

    public function marquerToutesLues(Request $request): \Illuminate\Http\Response
    {
        SisNotification::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('lue_le')
            ->update(['lue_le' => now()]);

        return response()->noContent();
    }
}
