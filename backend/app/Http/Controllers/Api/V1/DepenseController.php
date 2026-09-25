<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Tresorerie\CreateDepenseAction;
use App\Application\Tresorerie\DeleteDepenseAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\DepenseResource;
use App\Models\Depense;
use App\Support\ModePaiementRules;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class DepenseController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Depense::class);

        $items = Depense::query()
            ->with(['categorie', 'compte', 'media'])
            ->when($request->filled('categorie_id'), fn ($q) => $q->where('categorie_depense_id', $request->string('categorie_id')))
            ->when($request->filled('compte_id'), fn ($q) => $q->where('compte_tresorerie_id', $request->string('compte_id')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('date_depense', '>=', $request->string('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('date_depense', '<=', $request->string('to')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q')->toString().'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('libelle', 'like', $term)
                        ->orWhere('reference', 'like', $term)
                        ->orWhere('notes', 'like', $term);
                });
            })
            ->latest('date_depense')
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return DepenseResource::collection($items);
    }

    public function store(Request $request, CreateDepenseAction $action): DepenseResource
    {
        $this->authorize('create', Depense::class);

        $data = $request->validate([
            'categorie_depense_id' => ['required', 'uuid', 'exists:categories_depense,id'],
            'libelle' => ['required', 'string', 'max:255'],
            'montant' => ['required', 'numeric', 'min:0.01'],
            'date_depense' => ['required', 'date'],
            'compte_tresorerie_id' => ['required', 'uuid', 'exists:comptes_tresorerie,id'],
            'mode' => ModePaiementRules::required(),
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        return new DepenseResource($action->execute($data));
    }

    public function show(Depense $depense): DepenseResource
    {
        $this->authorize('view', $depense);

        return new DepenseResource($depense->load(['categorie', 'compte', 'media']));
    }

    public function destroy(Depense $depense, DeleteDepenseAction $action): Response
    {
        $this->authorize('delete', $depense);
        $action->execute($depense);

        return response()->noContent();
    }
}
