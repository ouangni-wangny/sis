<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Zone\SyncZoneControleursAction;
use App\Application\Zone\SyncZoneReleveAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Zone\StoreZoneRequest;
use App\Http\Requests\Zone\SyncZoneControleursRequest;
use App\Http\Requests\Zone\SyncZoneReleveRequest;
use App\Http\Requests\Zone\UpdateZoneRequest;
use App\Http\Resources\ZoneResource;
use App\Models\Zone;
use App\Support\ListQuery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ZoneController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Zone::class);

        $zones = Zone::query()
            ->with(['controleurs' => fn ($q) => $q->avecContratValide()])
            ->withCount(['sites', 'controleurs' => fn ($q) => $q->avecContratValide()])
            ->latest();

        return ZoneResource::collection(ListQuery::paginateOrAll($zones, $request));
    }

    public function store(StoreZoneRequest $request): ZoneResource
    {
        $this->authorize('create', Zone::class);

        return new ZoneResource(
            Zone::query()->create($request->validated())->loadCount(['sites', 'controleurs'])
        );
    }

    public function show(Zone $zone): ZoneResource
    {
        $this->authorize('view', $zone);

        return new ZoneResource(
            $zone->load(['controleurs' => fn ($q) => $q->avecContratValide()])
                ->loadCount(['sites', 'controleurs' => fn ($q) => $q->avecContratValide()])
        );
    }

    public function update(UpdateZoneRequest $request, Zone $zone): ZoneResource
    {
        $this->authorize('update', $zone);

        $zone->update($request->validated());

        return new ZoneResource(
            $zone->fresh()->load(['controleurs' => fn ($q) => $q->avecContratValide()])
                ->loadCount(['sites', 'controleurs' => fn ($q) => $q->avecContratValide()])
        );
    }

    public function syncControleurs(
        SyncZoneControleursRequest $request,
        Zone $zone,
        SyncZoneControleursAction $action,
    ): ZoneResource {
        $this->authorize('update', $zone);

        $zone = $action->execute($zone, $request->validated('agent_ids') ?? []);

        return new ZoneResource(
            $zone->load(['controleurs' => fn ($q) => $q->avecContratValide()])
                ->loadCount(['sites', 'controleurs' => fn ($q) => $q->avecContratValide()])
        );
    }

    public function syncReleve(
        SyncZoneReleveRequest $request,
        Zone $zone,
        SyncZoneReleveAction $action,
    ): ZoneResource {
        $this->authorize('update', $zone);

        $data = $request->validated();
        $zone = $action->execute(
            $zone,
            $data['releve_depuis'],
            $data['releve_jusque'],
            $data['ordre_agent_ids'] ?? null,
        );

        return new ZoneResource(
            $zone->load(['controleurs' => fn ($q) => $q->avecContratValide()])
                ->loadCount(['sites', 'controleurs' => fn ($q) => $q->avecContratValide()])
        );
    }

    public function destroy(Zone $zone): Response
    {
        $this->authorize('delete', $zone);

        $zone->delete();

        return response()->noContent();
    }
}
