<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategorieDepenseResource;
use App\Models\CategorieDepense;
use App\Models\Depense;
use App\Support\ListQuery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class CategorieDepenseController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->assertCanView($request);

        $items = CategorieDepense::query()
            ->when($request->boolean('actif_only'), fn ($q) => $q->where('actif', true))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q')->toString().'%';
                $q->where('libelle', 'like', $term);
            })
            ->orderBy('libelle');

        return CategorieDepenseResource::collection(ListQuery::paginateOrAll($items, $request));
    }

    public function store(Request $request): CategorieDepenseResource
    {
        $this->assertCanManage($request);

        $data = $request->validate([
            'libelle' => ['required', 'string', 'max:255', 'unique:categories_depense,libelle'],
            'actif' => ['sometimes', 'boolean'],
        ]);

        $row = CategorieDepense::query()->create([
            'libelle' => $data['libelle'],
            'actif' => $data['actif'] ?? true,
        ]);

        return new CategorieDepenseResource($row);
    }

    public function update(Request $request, CategorieDepense $categorieDepense): CategorieDepenseResource
    {
        $this->assertCanManage($request);

        $data = $request->validate([
            'libelle' => ['sometimes', 'string', 'max:255', 'unique:categories_depense,libelle,'.$categorieDepense->id],
            'actif' => ['sometimes', 'boolean'],
        ]);

        $categorieDepense->update($data);

        return new CategorieDepenseResource($categorieDepense->fresh());
    }

    public function destroy(Request $request, CategorieDepense $categorieDepense): Response
    {
        $this->assertCanManage($request);

        $used = Depense::query()->where('categorie_depense_id', $categorieDepense->id)->exists();
        if ($used) {
            throw ValidationException::withMessages([
                'categorie' => 'Cette catégorie est utilisée par des dépenses. Désactivez-la plutôt.',
            ]);
        }

        $categorieDepense->delete();

        return response()->noContent();
    }

    private function assertCanView(Request $request): void
    {
        $user = $request->user();
        abort_unless(
            $user?->can('grades.manage')
            || $user?->can('tresorerie.manage')
            || $user?->can('tresorerie.view')
            || $user?->can('depenses.view')
            || $user?->can('depenses.manage')
            || $user?->hasRole('super-admin'),
            403,
        );
    }

    private function assertCanManage(Request $request): void
    {
        $user = $request->user();
        abort_unless(
            $user?->can('grades.manage')
            || $user?->can('tresorerie.manage')
            || $user?->hasRole('super-admin'),
            403,
        );
    }
}
