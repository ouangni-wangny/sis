<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Operation\SignalerAnomalieAction;
use App\Application\Operation\UpdateAnomalieStatutAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Anomalie\StoreAnomalieRequest;
use App\Http\Requests\Anomalie\UpdateAnomalieStatutRequest;
use App\Http\Resources\AnomalieResource;
use App\Models\Anomalie;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AnomalieController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Anomalie::class);

        $items = Anomalie::query()
            ->with(['media', 'site', 'signalePar'])
            ->when($request->site_id, fn ($q, $v) => $q->where('site_id', $v))
            ->when($request->boolean('a_traiter'), fn ($q) => $q->whereIn('statut', ['ouverte', 'en_cours']))
            ->when(
                ! $request->boolean('a_traiter') && $request->statut,
                fn ($q, $v) => $q->where('statut', $v),
            )
            ->when($request->gravite, fn ($q, $v) => $q->where('gravite', $v))
            ->when($request->type, fn ($q, $v) => $q->where('type', $v))
            ->when($request->boolean('recent_30j'), fn ($q) => $q->whereDate('signale_at', '>=', now()->subDays(30)->toDateString()))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q')->toString().'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('commentaire', 'like', $term)
                        ->orWhere('type', 'like', $term)
                        ->orWhere('gravite', 'like', $term)
                        ->orWhere('statut', 'like', $term)
                        ->orWhereHas('site', function ($site) use ($term) {
                            $site->where('nom', 'like', $term);
                        })
                        ->orWhereHas('signalePar', function ($agent) use ($term) {
                            $agent->where('nom', 'like', $term)
                                ->orWhere('prenom', 'like', $term)
                                ->orWhere('matricule', 'like', $term);
                        });
                });
            })
            ->latest('signale_at')
            ->paginate($request->integer('per_page', 15));

        return AnomalieResource::collection($items);
    }

    public function store(StoreAnomalieRequest $request, SignalerAnomalieAction $action): AnomalieResource
    {
        $this->authorize('create', Anomalie::class);
        $photos = $request->file('photos', []);
        if (! is_array($photos)) {
            $photos = $photos ? [$photos] : [];
        }

        return new AnomalieResource($action->execute($request->safe()->except('photos'), $photos));
    }

    public function show(Anomalie $anomalie): AnomalieResource
    {
        $this->authorize('view', $anomalie);

        return new AnomalieResource($anomalie->load(['media', 'site', 'signalePar', 'assigneA']));
    }

    public function updateStatut(UpdateAnomalieStatutRequest $request, Anomalie $anomalie, UpdateAnomalieStatutAction $action): AnomalieResource
    {
        $this->authorize('update', $anomalie);
        $data = $request->validated();

        return new AnomalieResource($action->execute($anomalie, $data['statut'], $data['assigne_a_id'] ?? null));
    }
}