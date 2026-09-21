<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Referentiel\SoftDeleteSiteCascadeAction;
use App\Application\Site\CreateSiteWithPostesAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Site\StoreSiteRequest;
use App\Http\Requests\Site\UpdateSiteRequest;
use App\Http\Resources\SiteResource;
use App\Models\Site;
use App\Support\ListQuery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class SiteController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Site::class);

        $sites = Site::query()
            ->with(['client', 'zone'])
            ->when($request->client_id, fn ($q, $v) => $q->where('client_id', $v))
            ->when($request->zone_id, fn ($q, $v) => $q->where('zone_id', $v))
            ->when($request->has('interne'), fn ($q) => $q->where('interne', $request->boolean('interne')))
            ->when($request->q, fn ($q, $v) => $q->where('nom', 'like', "%{$v}%"))
            ->latest();

        return SiteResource::collection(ListQuery::paginateOrAll($sites, $request));
    }

    public function store(StoreSiteRequest $request, CreateSiteWithPostesAction $action): SiteResource
    {
        $this->authorize('create', Site::class);
        $data = $request->validated();
        $postes = $data['postes'] ?? [];
        unset($data['postes']);

        return new SiteResource($action->execute($data, $postes));
    }

    public function show(Site $site): SiteResource
    {
        $this->authorize('view', $site);

        return new SiteResource($site->load(['client', 'zone', 'postes', 'checkpoints']));
    }

    public function update(UpdateSiteRequest $request, Site $site): SiteResource
    {
        $this->authorize('update', $site);
        $site->update($request->validated());

        return new SiteResource($site->load(['client', 'zone', 'postes']));
    }

    public function destroy(Site $site, SoftDeleteSiteCascadeAction $action): Response
    {
        $this->authorize('delete', $site);
        $action->execute($site);

        return response()->noContent();
    }
}
