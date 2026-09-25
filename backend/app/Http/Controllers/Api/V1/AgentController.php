<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Agent\CreateAgentWithMobileAccountAction;
use App\Application\Agent\GenererListeAgentsPdfAction;
use App\Application\Agent\SoftDeleteAgentAction;
use App\Application\Agent\SyncRondierPerimetreAction;
use App\Application\Agent\UpdateAgentAction;
use App\Application\Agent\UploadAgentDocumentAction;
use App\Application\Agent\UploadAgentPhotoAction;
use App\Application\Rh\AjusterSoldeCongesAction;
use App\Domain\Shared\Enums\StatutRonde;
use App\Domain\Shared\Enums\StatutVacation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\StoreAgentRequest;
use App\Http\Requests\Agent\SyncPerimetreRequest;
use App\Http\Requests\Agent\UpdateAgentRequest;
use App\Http\Requests\Agent\UploadAgentPhotoRequest;
use App\Http\Resources\AgentResource;
use App\Http\Resources\BulletinPaieResource;
use App\Models\Agent;
use App\Models\BulletinPaie;
use App\Models\Ronde;
use App\Models\SoldeCongesMouvement;
use App\Models\Vacation;
use App\Support\ListQuery;
use App\Support\RhAuthorization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AgentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Agent::class);

        $agents = GenererListeAgentsPdfAction::filteredQuery($request)
            ->with(['grade', 'villeRef', 'media', 'perimetres.zone', 'contratActif', 'user', 'posteSiege.site'])
            ->latest();

        return AgentResource::collection(ListQuery::paginateOrAll($agents, $request));
    }

    public function exportPdf(Request $request, GenererListeAgentsPdfAction $action): SymfonyResponse
    {
        $this->authorize('viewAny', Agent::class);

        return $action->execute($request);
    }

    public function store(StoreAgentRequest $request, CreateAgentWithMobileAccountAction $action): JsonResponse
    {
        $this->authorize('create', Agent::class);

        return (new AgentResource($action->execute($request->validated())))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Agent $agent): AgentResource
    {
        $this->authorize('view', $agent);

        return new AgentResource($agent->load(['grade', 'villeRef', 'media', 'user', 'contratActif', 'perimetres.zone', 'posteSiege.site']));
    }

    public function update(UpdateAgentRequest $request, Agent $agent, UpdateAgentAction $action): AgentResource
    {
        $this->authorize('update', $agent);

        return new AgentResource($action->execute($agent, $request->validated()));
    }

    public function destroy(Agent $agent, SoftDeleteAgentAction $action): Response
    {
        $this->authorize('delete', $agent);

        $hasOpenVacation = Vacation::query()
            ->where('agent_id', $agent->id)
            ->whereNotIn('statut', [StatutVacation::Annulee, StatutVacation::Terminee])
            ->exists();

        if ($hasOpenVacation) {
            throw ValidationException::withMessages([
                'agent' => 'Impossible de supprimer : cet agent a encore des vacations planifiées ou en cours.',
            ]);
        }

        $hasOpenRonde = Ronde::query()
            ->where('agent_id', $agent->id)
            ->whereIn('statut', [StatutRonde::Planifiee, StatutRonde::EnCours])
            ->exists();

        if ($hasOpenRonde) {
            throw ValidationException::withMessages([
                'agent' => 'Impossible de supprimer : cet agent a une ronde planifiée ou en cours.',
            ]);
        }

        $action->execute($agent);

        return response()->noContent();
    }

    public function storePhoto(UploadAgentPhotoRequest $request, Agent $agent, UploadAgentPhotoAction $action): AgentResource
    {
        $this->authorize('update', $agent);

        return new AgentResource($action->execute($agent, $request->file('photo')));
    }

    public function syncPerimetre(SyncPerimetreRequest $request, Agent $agent, SyncRondierPerimetreAction $action): AgentResource
    {
        $this->authorize('update', $agent);

        return new AgentResource($action->execute($agent, $request->validated('items')));
    }

    public function uploadDocument(
        Request $request,
        Agent $agent,
        string $collection,
        UploadAgentDocumentAction $action,
    ): AgentResource {
        abort_unless(RhAuthorization::canManageDocuments($request->user()), 403);
        $this->authorize('update', $agent);

        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        return new AgentResource($action->execute($agent, $collection, $request->file('file')));
    }

    public function downloadDocument(Request $request, Agent $agent, string $collection): BinaryFileResponse
    {
        $this->authorize('view', $agent);
        abort_unless(in_array($collection, ['piece_identite', 'permis'], true), 404);

        $media = $agent->getFirstMedia($collection);
        abort_unless($media, 404);

        return response()->download($media->getPath(), $media->file_name);
    }

    public function congesMouvements(Request $request, Agent $agent): AnonymousResourceCollection
    {
        $this->authorize('view', $agent);

        $items = SoldeCongesMouvement::query()
            ->where('agent_id', $agent->id)
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return JsonResource::collection($items);
    }

    public function bulletins(Request $request, Agent $agent): AnonymousResourceCollection
    {
        abort_unless(RhAuthorization::canViewPaie($request->user()), 403);
        $this->authorize('view', $agent);

        $items = BulletinPaie::query()
            ->where('agent_id', $agent->id)
            ->with(['periodePaie', 'media'])
            ->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->string('statut')))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return BulletinPaieResource::collection($items);
    }

    public function accorderConges(
        Request $request,
        Agent $agent,
        AjusterSoldeCongesAction $action,
    ): AgentResource {
        abort_unless(RhAuthorization::canManageAbsences($request->user()), 403);
        $this->authorize('update', $agent);

        $data = $request->validate([
            'jours' => ['nullable', 'numeric', 'min:0.5', 'max:60'],
        ]);

        $action->accorderAnnuel($agent, $data['jours'] ?? null, $request->user());

        return new AgentResource($agent->fresh());
    }
}
