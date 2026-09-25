<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ModePaiementParamResource;
use App\Models\ModePaiementParam;
use App\Support\ListQuery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ModePaiementParamController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->assertCanView($request);

        $items = ModePaiementParam::query()
            ->when($request->boolean('actif_only'), fn ($q) => $q->where('actif', true))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q')->toString().'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('libelle', 'like', $term)
                        ->orWhere('code', 'like', $term);
                });
            })
            ->orderBy('ordre')
            ->orderBy('libelle');

        return ModePaiementParamResource::collection(ListQuery::paginateOrAll($items, $request));
    }

    public function store(Request $request): ModePaiementParamResource
    {
        $this->assertCanManage($request);

        $data = $request->validate([
            'libelle' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', 'alpha_dash', 'unique:modes_paiement,code'],
            'actif' => ['sometimes', 'boolean'],
            'ordre' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        $code = $data['code'] ?? Str::slug($data['libelle'], '_');
        if ($code === '') {
            throw ValidationException::withMessages([
                'code' => 'Impossible de dériver un code valide depuis le libellé.',
            ]);
        }

        if (ModePaiementParam::query()->where('code', $code)->exists()) {
            throw ValidationException::withMessages([
                'code' => 'Ce code existe déjà.',
            ]);
        }

        $row = ModePaiementParam::query()->create([
            'code' => $code,
            'libelle' => $data['libelle'],
            'actif' => $data['actif'] ?? true,
            'ordre' => $data['ordre'] ?? ((int) ModePaiementParam::query()->max('ordre') + 1),
        ]);

        return new ModePaiementParamResource($row);
    }

    public function update(Request $request, ModePaiementParam $modePaiementParam): ModePaiementParamResource
    {
        $this->assertCanManage($request);

        $data = $request->validate([
            'libelle' => ['sometimes', 'string', 'max:255'],
            'actif' => ['sometimes', 'boolean'],
            'ordre' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        // Le code n’est pas modifiable (référencé dans les mouvements historiques).
        $modePaiementParam->update($data);

        return new ModePaiementParamResource($modePaiementParam->fresh());
    }

    public function destroy(Request $request, ModePaiementParam $modePaiementParam): Response
    {
        $this->assertCanManage($request);

        $used = \App\Models\MouvementTresorerie::query()
            ->where('mode', $modePaiementParam->code)
            ->exists()
            || \App\Models\Paiement::query()->where('mode', $modePaiementParam->code)->exists()
            || \App\Models\Depense::query()->where('mode', $modePaiementParam->code)->exists()
            || \App\Models\BulletinPaie::query()->where('mode_paiement', $modePaiementParam->code)->exists();

        if ($used) {
            throw ValidationException::withMessages([
                'mode' => 'Ce mode est utilisé dans des mouvements. Désactivez-le plutôt.',
            ]);
        }

        $modePaiementParam->delete();

        return response()->noContent();
    }

    private function assertCanView(Request $request): void
    {
        $user = $request->user();
        abort_unless(
            $user?->can('grades.manage')
            || $user?->can('tresorerie.manage')
            || $user?->can('tresorerie.view')
            || $user?->can('paiements.view')
            || $user?->can('paiements.create')
            || $user?->can('paie.view')
            || $user?->can('paie.manage')
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
