<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Operation\CreateBulkVacationsAction;
use App\Application\Operation\CreateVacationAction;
use App\Application\Operation\DetecterConflitsVacationAction;
use App\Application\Operation\RecouvrirVacationAction;
use App\Application\Operation\UpdateVacationAction;
use App\Application\Shared\RondierPerimetreGuard;
use App\Domain\Shared\Enums\StatutRonde;
use App\Domain\Shared\Enums\StatutVacation;
use App\Domain\Shared\Enums\TypeAgent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vacation\RecouvrirVacationRequest;
use App\Http\Requests\Vacation\StoreBulkVacationsRequest;
use App\Http\Requests\Vacation\StoreVacationRequest;
use App\Http\Requests\Vacation\UpdateVacationRequest;
use App\Http\Resources\VacationResource;
use App\Models\Ronde;
use App\Models\Vacation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class VacationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Vacation::class);

        // Un contrôleur ne voit que les agents en poste dans son périmètre assigné.
        $controleurAgent = $request->user()?->agent?->type === TypeAgent::Controleur
            ? $request->user()->agent
            : null;

        $items = Vacation::query()
            ->with(['agent', 'site', 'poste'])
            ->when($controleurAgent, function ($q) use ($controleurAgent) {
                $q->whereIn('site_id', RondierPerimetreGuard::authorizedSiteIds($controleurAgent));
            })
            ->when($request->agent_id, fn ($q, $v) => $q->where('agent_id', $v))
            ->when($request->site_id, fn ($q, $v) => $q->where('site_id', $v))
            ->when($request->poste_id, fn ($q, $v) => $q->where('poste_id', $v))
            ->when($request->statut, fn ($q, $v) => $q->where('statut', $v))
            ->when($request->boolean('en_poste'), function ($q) use ($request) {
                $date = $request->date('date')?->format('Y-m-d') ?? now()->toDateString();
                $q->whereIn('statut', [StatutVacation::Planifiee, StatutVacation::EnCours])
                    ->whereDate('date_debut', '<=', $date)
                    ->where(function ($inner) use ($date) {
                        $inner->whereNull('date_fin')->orWhereDate('date_fin', '>=', $date);
                    })
                    ->whereHas('agent', fn ($a) => $a->where('type', TypeAgent::Agent));
            })
            ->when($request->has('site_interne'), function ($q) use ($request) {
                $q->whereHas('site', fn ($s) => $s->where('interne', $request->boolean('site_interne')));
            })
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q')->toString().'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('statut', 'like', $term)
                        ->orWhereHas('agent', function ($a) use ($term) {
                            $a->where('nom', 'like', $term)
                                ->orWhere('prenom', 'like', $term)
                                ->orWhere('matricule', 'like', $term);
                        })
                        ->orWhereHas('site', fn ($s) => $s->where('nom', 'like', $term))
                        ->orWhereHas('poste', fn ($p) => $p->where('nom', 'like', $term));
                });
            })
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return VacationResource::collection($items);
    }

    public function store(StoreVacationRequest $request, CreateVacationAction $action): VacationResource
    {
        $this->authorize('create', Vacation::class);

        return new VacationResource($action->execute($request->validated()));
    }

    public function storeBulk(
        StoreBulkVacationsRequest $request,
        CreateBulkVacationsAction $action,
    ): JsonResponse {
        $this->authorize('create', Vacation::class);

        $result = $action->execute($request->validated());

        return response()->json([
            'data' => [
                'created' => $result['created'],
                'removed' => $result['removed'],
            ],
        ], 201);
    }

    public function show(Vacation $vacation): VacationResource
    {
        $this->authorize('view', $vacation);

        return new VacationResource($vacation->load(['agent', 'site', 'poste']));
    }

    public function update(UpdateVacationRequest $request, Vacation $vacation, UpdateVacationAction $action): VacationResource
    {
        $this->authorize('update', $vacation);

        return new VacationResource($action->execute($vacation, $request->validated()));
    }

    public function destroy(Vacation $vacation): Response
    {
        $this->authorize('delete', $vacation);

        $hasOpenRonde = Ronde::query()
            ->where('vacation_id', $vacation->id)
            ->whereIn('statut', [StatutRonde::Planifiee, StatutRonde::EnCours])
            ->exists();

        if ($hasOpenRonde) {
            throw ValidationException::withMessages([
                'vacation' => 'Impossible de supprimer : une ronde liée est encore planifiée ou en cours.',
            ]);
        }

        $vacation->delete();

        return response()->noContent();
    }

    public function conflits(Request $request, DetecterConflitsVacationAction $action): JsonResponse
    {
        $request->validate([
            'agent_id' => ['required', 'uuid'],
            'date_debut' => ['required', 'date'],
            'date_fin' => ['nullable', 'date'],
            'heure_debut' => ['required', 'date_format:H:i'],
            'heure_fin' => ['required', 'date_format:H:i'],
        ]);

        $conflits = $action->execute(
            $request->string('agent_id')->toString(),
            $request->string('date_debut')->toString(),
            $request->string('heure_debut')->toString(),
            $request->string('heure_fin')->toString(),
            $request->input('date_fin'),
        );

        return response()->json([
            'data' => VacationResource::collection($conflits),
            'meta' => ['count' => $conflits->count()],
        ]);
    }

    public function recouvrir(
        RecouvrirVacationRequest $request,
        Vacation $vacation,
        RecouvrirVacationAction $action,
    ): VacationResource {
        $this->authorize('update', $vacation);

        return new VacationResource(
            $action->execute($vacation, $request->string('agent_id')->toString())
        );
    }
}