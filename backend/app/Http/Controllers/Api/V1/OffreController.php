<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Shared\Enums\StatutAbonnement;
use App\Http\Controllers\Controller;
use App\Http\Requests\Offre\StoreOffreRequest;
use App\Http\Requests\Offre\UpdateOffreRequest;
use App\Http\Resources\OffreResource;
use App\Models\Abonnement;
use App\Models\Offre;
use App\Support\ListQuery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class OffreController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Offre::class);

        $offres = Offre::query()
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q')->toString().'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('libelle', 'like', $term)
                        ->orWhere('description', 'like', $term);
                });
            })
            ->latest();

        return OffreResource::collection(ListQuery::paginateOrAll($offres, $request));
    }

    public function store(StoreOffreRequest $request): OffreResource
    {
        $this->authorize('create', Offre::class);

        return new OffreResource(Offre::query()->create($request->validated()));
    }

    public function show(Offre $offre): OffreResource
    {
        $this->authorize('view', $offre);

        return new OffreResource($offre);
    }

    public function update(UpdateOffreRequest $request, Offre $offre): OffreResource
    {
        $this->authorize('update', $offre);

        $offre->update($request->validated());

        return new OffreResource($offre);
    }

    public function destroy(Offre $offre): Response
    {
        $this->authorize('delete', $offre);

        $hasActiveAbo = Abonnement::query()
            ->where('offre_id', $offre->id)
            ->where('statut', StatutAbonnement::Actif)
            ->exists();

        if ($hasActiveAbo) {
            throw ValidationException::withMessages([
                'offre' => 'Impossible de supprimer : des abonnements actifs utilisent cette offre. Désactivez-la plutôt.',
            ]);
        }

        $offre->delete();

        return response()->noContent();
    }
}
