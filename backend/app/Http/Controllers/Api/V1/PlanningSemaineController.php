<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Operation\DupliquerPlanningSemaineAction;
use App\Application\Operation\PreparerProchainesSemainesAction;
use App\Application\Operation\PublierPlanningSemaineAction;
use App\Application\Operation\UpsertPlanningSemaineAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\PlanningSemaine\DupliquerPlanningSemaineRequest;
use App\Http\Requests\PlanningSemaine\PreparerProchainesSemainesRequest;
use App\Http\Requests\PlanningSemaine\UpsertPlanningSemaineRequest;
use App\Http\Resources\PlanningSemaineResource;
use App\Models\PlanningSemaine;
use App\Models\Vacation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PlanningSemaineController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Vacation::class);

        $data = $request->validate([
            'poste_id' => ['required', 'uuid', 'exists:postes,id'],
            'annee_iso' => ['required', 'integer', 'min:2020', 'max:2100'],
            'semaine_iso' => ['required', 'integer', 'min:1', 'max:53'],
        ]);

        $planning = PlanningSemaine::query()
            ->with(['creneaux.agent', 'poste.site'])
            ->where('poste_id', $data['poste_id'])
            ->where('annee_iso', $data['annee_iso'])
            ->where('semaine_iso', $data['semaine_iso'])
            ->first();

        if (! $planning) {
            return response()->json([
                'data' => [
                    'id' => null,
                    'poste_id' => $data['poste_id'],
                    'annee_iso' => (int) $data['annee_iso'],
                    'semaine_iso' => (int) $data['semaine_iso'],
                    'statut' => 'brouillon',
                    'creneaux' => [],
                    'date_lundi' => (new PlanningSemaine([
                        'annee_iso' => (int) $data['annee_iso'],
                        'semaine_iso' => (int) $data['semaine_iso'],
                    ]))->lundi()->toDateString(),
                    'date_dimanche' => (new PlanningSemaine([
                        'annee_iso' => (int) $data['annee_iso'],
                        'semaine_iso' => (int) $data['semaine_iso'],
                    ]))->dimanche()->toDateString(),
                ],
            ]);
        }

        return response()->json([
            'data' => (new PlanningSemaineResource($planning))->resolve(),
        ]);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Vacation::class);

        $data = $request->validate([
            'poste_id' => ['required', 'uuid', 'exists:postes,id'],
            'annee_iso' => ['sometimes', 'integer'],
        ]);

        $items = PlanningSemaine::query()
            ->with(['creneaux.agent', 'poste.site'])
            ->where('poste_id', $data['poste_id'])
            ->when($request->filled('annee_iso'), fn ($q) => $q->where('annee_iso', (int) $request->annee_iso))
            ->orderByDesc('annee_iso')
            ->orderByDesc('semaine_iso')
            ->limit(60)
            ->get();

        return PlanningSemaineResource::collection($items);
    }

    public function upsert(
        UpsertPlanningSemaineRequest $request,
        UpsertPlanningSemaineAction $action,
    ): JsonResponse {
        $this->authorize('create', Vacation::class);

        $planning = $action->execute($request->validated());

        return response()->json([
            'data' => (new PlanningSemaineResource($planning))->resolve(),
        ]);
    }

    public function publier(
        Request $request,
        PublierPlanningSemaineAction $action,
    ): JsonResponse {
        $this->authorize('create', Vacation::class);

        $data = $request->validate([
            'poste_id' => ['required', 'uuid', 'exists:postes,id'],
            'annee_iso' => ['required', 'integer', 'min:2020', 'max:2100'],
            'semaine_iso' => ['required', 'integer', 'min:1', 'max:53'],
        ]);

        $planning = PlanningSemaine::query()
            ->where('poste_id', $data['poste_id'])
            ->where('annee_iso', $data['annee_iso'])
            ->where('semaine_iso', $data['semaine_iso'])
            ->firstOrFail();

        $planning = $action->execute($planning);

        return response()->json([
            'data' => (new PlanningSemaineResource($planning))->resolve(),
        ]);
    }

    public function dupliquer(
        DupliquerPlanningSemaineRequest $request,
        DupliquerPlanningSemaineAction $action,
    ): JsonResponse {
        $this->authorize('create', Vacation::class);

        $planning = $action->execute($request->validated());

        return response()->json([
            'data' => (new PlanningSemaineResource($planning))->resolve(),
        ]);
    }

    public function preparer(
        PreparerProchainesSemainesRequest $request,
        PreparerProchainesSemainesAction $action,
    ): JsonResponse {
        $this->authorize('create', Vacation::class);

        $data = $request->validated();
        $result = $action->execute(
            $data['poste_id'],
            (int) ($data['nombre'] ?? 4),
        );

        return response()->json([
            'data' => [
                'preparees' => $result['preparees'],
                'plannings' => PlanningSemaineResource::collection(
                    collect($result['plannings']),
                )->resolve(),
            ],
        ]);
    }
}
