<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Commercial\UpsertAbonnementAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Abonnement\StoreAbonnementRequest;
use App\Http\Requests\Abonnement\UpdateAbonnementRequest;
use App\Http\Resources\AbonnementResource;
use App\Models\Abonnement;
use App\Support\ListQuery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class AbonnementController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Abonnement::class);

        $items = Abonnement::query()
            ->with(['client', 'offre', 'site'])
            ->when($request->client_id, fn ($q, $v) => $q->where('client_id', $v))
            ->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->string('statut')))
            ->when($request->filled('periodicite'), fn ($q) => $q->where('periodicite', $request->string('periodicite')))
            ->when($request->boolean('echeance_30j'), function ($q) {
                $q->where('statut', 'actif')
                    ->whereNotNull('prochaine_facture_le')
                    ->whereDate('prochaine_facture_le', '<=', now()->addDays(30)->toDateString());
            })
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q')->toString().'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('statut', 'like', $term)
                        ->orWhereHas('client', function ($c) use ($term) {
                            $c->where('raison_sociale', 'like', $term)
                                ->orWhere('nom_responsable', 'like', $term);
                        })
                        ->orWhereHas('offre', fn ($o) => $o->where('libelle', 'like', $term))
                        ->orWhere('designation', 'like', $term)
                        ->orWhereHas('site', fn ($s) => $s->where('nom', 'like', $term));
                });
            })
            ->latest();

        return AbonnementResource::collection(ListQuery::paginateOrAll($items, $request));
    }

    public function store(StoreAbonnementRequest $request, UpsertAbonnementAction $action): AbonnementResource
    {
        $this->authorize('create', Abonnement::class);

        return new AbonnementResource($action->create($request->validated()));
    }

    public function show(Abonnement $abonnement): AbonnementResource
    {
        $this->authorize('view', $abonnement);

        return new AbonnementResource($abonnement->load([
            'client',
            'offre',
            'site',
            'lignes.offre',
            'factures' => fn ($q) => $q->latest()->limit(20),
        ]));
    }

    public function update(UpdateAbonnementRequest $request, Abonnement $abonnement, UpsertAbonnementAction $action): AbonnementResource
    {
        $this->authorize('update', $abonnement);

        return new AbonnementResource($action->update($abonnement, $request->validated()));
    }

    public function destroy(Abonnement $abonnement): Response
    {
        $this->authorize('delete', $abonnement);
        $abonnement->delete();

        return response()->noContent();
    }
}
