<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Rh\AbsenceOperationResult;
use App\Application\Rh\DeleteAbsenceAction;
use App\Application\Rh\UpsertAbsenceAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Absence\StoreAbsenceRequest;
use App\Http\Requests\Absence\UpdateAbsenceRequest;
use App\Http\Resources\AbsenceResource;
use App\Models\Absence;
use App\Support\RhAuthorization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AbsenceController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless(RhAuthorization::canViewAbsences($request->user()), 403);

        $items = Absence::query()
            ->with('agent')
            ->when($request->agent_id, fn ($q, $v) => $q->where('agent_id', $v))
            ->when($request->statut, fn ($q, $v) => $q->where('statut', $v))
            ->when($request->type, fn ($q, $v) => $q->where('type', $v))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(function ($qq) use ($term) {
                    $qq->where('motif', 'like', $term)
                        ->orWhere('statut', 'like', $term)
                        ->orWhere('type', 'like', $term)
                        ->orWhereHas('agent', function ($agent) use ($term) {
                            $agent->where('nom', 'like', $term)
                                ->orWhere('prenom', 'like', $term)
                                ->orWhere('matricule', 'like', $term);
                        });
                });
            })
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return AbsenceResource::collection($items);
    }

    public function store(StoreAbsenceRequest $request, UpsertAbsenceAction $action): AbsenceResource
    {
        abort_unless(RhAuthorization::canManageAbsences($request->user()), 403);

        return $this->toResource($action->create($request->validated()));
    }

    public function update(
        UpdateAbsenceRequest $request,
        Absence $absence,
        UpsertAbsenceAction $action,
    ): AbsenceResource {
        abort_unless(RhAuthorization::canManageAbsences($request->user()), 403);

        return $this->toResource($action->update($absence, $request->validated()));
    }

    public function destroy(
        Request $request,
        Absence $absence,
        DeleteAbsenceAction $action,
    ): JsonResponse {
        abort_unless(RhAuthorization::canManageAbsences($request->user()), 403);

        return $this->toResource($action->execute($absence))->response();
    }

    private function toResource(AbsenceOperationResult $result): AbsenceResource
    {
        $result->absence->setAttribute(
            'vacations_marquees_a_recouvrir',
            $result->vacationsMarqueesARecouvrir,
        );
        $result->absence->setAttribute(
            'vacations_restaurees',
            $result->vacationsRestaurees,
        );

        return new AbsenceResource($result->absence);
    }
}
