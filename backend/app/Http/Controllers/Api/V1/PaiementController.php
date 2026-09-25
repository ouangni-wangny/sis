<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Commercial\UpsertPaiementAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Paiement\StorePaiementRequest;
use App\Http\Requests\Paiement\UpdatePaiementRequest;
use App\Http\Resources\PaiementResource;
use App\Models\Paiement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class PaiementController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Paiement::class);

        $items = Paiement::query()
            ->with(['facture.client', 'compteTresorerie'])
            ->when($request->facture_id, fn ($q, $v) => $q->where('facture_id', $v))
            ->when($request->filled('client_id'), function ($q) use ($request) {
                $clientId = $request->string('client_id')->toString();
                $q->whereHas('facture', fn ($f) => $f->where('client_id', $clientId));
            })
            ->when($request->filled('mode'), fn ($q) => $q->where('mode', $request->string('mode')))
            ->when($request->boolean('recent_30j'), fn ($q) => $q->whereDate('date_paiement', '>=', now()->subDays(30)->toDateString()))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q')->toString().'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('reference', 'like', $term)
                        ->orWhere('mode', 'like', $term)
                        ->orWhere('notes', 'like', $term)
                        ->orWhereHas('facture', function ($f) use ($term) {
                            $f->where('numero', 'like', $term)
                                ->orWhereHas('client', function ($c) use ($term) {
                                    $c->where('raison_sociale', 'like', $term);
                                });
                        });
                });
            })
            ->latest('date_paiement')
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return PaiementResource::collection($items);
    }

    public function store(StorePaiementRequest $request, UpsertPaiementAction $action): PaiementResource
    {
        $this->authorize('create', Paiement::class);

        return new PaiementResource($action->create($request->validated()));
    }

    public function show(Paiement $paiement): PaiementResource
    {
        $this->authorize('view', $paiement);

        return new PaiementResource($paiement->load(['facture.client', 'compteTresorerie']));
    }

    public function update(
        UpdatePaiementRequest $request,
        Paiement $paiement,
        UpsertPaiementAction $action,
    ): PaiementResource {
        $this->authorize('update', $paiement);

        return new PaiementResource($action->update($paiement, $request->validated()));
    }

    public function destroy(Paiement $paiement, UpsertPaiementAction $action): Response
    {
        $this->authorize('delete', $paiement);
        $action->delete($paiement);

        return response()->noContent();
    }
}
