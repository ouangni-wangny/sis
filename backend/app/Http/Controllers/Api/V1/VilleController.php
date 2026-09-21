<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ville\StoreVilleRequest;
use App\Http\Requests\Ville\UpdateVilleRequest;
use App\Http\Resources\VilleResource;
use App\Models\Ville;
use App\Support\ListQuery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class VilleController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless(
            $request->user()?->can('grades.manage') || $request->user()?->can('agents.view'),
            403,
        );

        $villes = Ville::query()
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q')->toString().'%';
                $q->where('libelle', 'like', $term);
            })
            ->orderBy('libelle');

        return VilleResource::collection(ListQuery::paginateOrAll($villes, $request));
    }

    public function store(StoreVilleRequest $request): VilleResource
    {
        abort_unless($request->user()?->can('grades.manage'), 403);

        return new VilleResource(Ville::query()->create($request->validated()));
    }

    public function show(Request $request, Ville $ville): VilleResource
    {
        abort_unless(
            $request->user()?->can('grades.manage') || $request->user()?->can('agents.view'),
            403,
        );

        return new VilleResource($ville);
    }

    public function update(UpdateVilleRequest $request, Ville $ville): VilleResource
    {
        abort_unless($request->user()?->can('grades.manage'), 403);

        $ville->update($request->validated());

        // Garder le libellé texte des agents aligné.
        if ($ville->wasChanged('libelle')) {
            $ville->agents()->update(['ville' => $ville->libelle]);
        }

        return new VilleResource($ville);
    }

    public function destroy(Request $request, Ville $ville): Response
    {
        abort_unless($request->user()?->can('grades.manage'), 403);

        $ville->delete();

        return response()->noContent();
    }
}
