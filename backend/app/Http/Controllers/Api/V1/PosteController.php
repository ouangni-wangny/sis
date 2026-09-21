<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Reporting\GetPosteCoverageAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Poste\StorePosteRequest;
use App\Http\Requests\Poste\UpdatePosteRequest;
use App\Http\Resources\PosteResource;
use App\Models\Poste;
use App\Models\Site;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class PosteController extends Controller
{
    public function coverage(Request $request, GetPosteCoverageAction $action): JsonResponse
    {
        abort_unless(
            $request->user()?->can('vacations.view') || $request->user()?->can('postes.view'),
            403,
        );

        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'site_id' => ['nullable', 'uuid'],
        ]);

        $coverage = $action->execute($data['date'], $data['site_id'] ?? null);

        return response()->json([
            'data' => $coverage,
            'meta' => ['date' => $data['date'], 'count' => $coverage->count()],
        ]);
    }

    public function indexAll(Request $request): AnonymousResourceCollection
    {
        abort_unless(
            $request->user()?->can('postes.view') || $request->user()?->can('sites.view'),
            403,
        );

        $items = Poste::query()
            ->with('site')
            ->when($request->site_id, fn ($q, $v) => $q->where('site_id', $v))
            ->when(
                $request->has('site_interne'),
                fn ($q) => $q->whereHas('site', fn ($s) => $s->where('interne', $request->boolean('site_interne'))),
            )
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q')->toString().'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('nom', 'like', $term)
                        ->orWhereHas('site', fn ($s) => $s->where('nom', 'like', $term));
                });
            })
            ->latest();

        return PosteResource::collection(ListQuery::paginateOrAll($items, $request));
    }

    public function index(Request $request, Site $site): AnonymousResourceCollection
    {
        abort_unless(
            $request->user()?->can('postes.view') || $request->user()?->can('sites.view'),
            403,
        );

        return PosteResource::collection($site->postes()->latest()->get());
    }

    public function store(StorePosteRequest $request, Site $site): PosteResource
    {
        abort_unless($request->user()?->can('postes.manage'), 403);

        $poste = $site->postes()->create($request->validated());

        return new PosteResource($poste);
    }

    public function update(UpdatePosteRequest $request, Site $site, Poste $poste): PosteResource
    {
        abort_unless($request->user()?->can('postes.manage'), 403);
        abort_unless($poste->site_id === $site->id, 404);

        $poste->update($request->validated());

        return new PosteResource($poste->fresh());
    }

    public function destroy(Request $request, Site $site, Poste $poste): Response
    {
        abort_unless($request->user()?->can('postes.manage'), 403);
        abort_unless($poste->site_id === $site->id, 404);
        $poste->delete();

        return response()->noContent();
    }
}
