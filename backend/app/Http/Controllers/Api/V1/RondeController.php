<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Operation\DemarrerRondeAction;
use App\Application\Operation\PlanifierRondeAction;
use App\Application\Operation\ScannerCheckpointAction;
use App\Application\Operation\TerminerRondeAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ronde\ScannerCheckpointRequest;
use App\Http\Requests\Ronde\StoreRondeRequest;
use App\Http\Resources\RondeResource;
use App\Models\Ronde;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RondeController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()?->can('rondes.view') || $request->user()?->can('rondes.manage'), 403);

        $user = $request->user();
        $agentId = $request->agent_id;

        // Un agent mobile ne voit que ses propres rondes.
        if ($user?->tokenCan('mobile') && $user->agent) {
            $agentId = $user->agent->id;
        }

        $rondes = Ronde::query()
            ->with(['agent', 'site'])
            ->when($request->statut, fn ($q, $v) => $q->where('statut', $v))
            ->when($agentId, fn ($q, $v) => $q->where('agent_id', $v))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q')->toString().'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('statut', 'like', $term)
                        ->orWhereHas('agent', function ($a) use ($term) {
                            $a->where('nom', 'like', $term)
                                ->orWhere('prenom', 'like', $term)
                                ->orWhere('matricule', 'like', $term);
                        })
                        ->orWhereHas('site', fn ($s) => $s->where('nom', 'like', $term));
                });
            })
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return RondeResource::collection($rondes);
    }

    public function store(StoreRondeRequest $request, PlanifierRondeAction $action): RondeResource
    {
        abort_unless($request->user()?->can('rondes.manage'), 403);

        return new RondeResource($action->execute($request->validated()));
    }

    public function show(Request $request, Ronde $ronde): RondeResource
    {
        abort_unless($request->user()?->can('rondes.view') || $request->user()?->can('rondes.manage'), 403);

        $user = $request->user();
        if ($user?->tokenCan('mobile') && $user->agent) {
            abort_unless($ronde->agent_id === $user->agent->id, 403);
        }

        return new RondeResource($ronde->load(['agent', 'site', 'rondeCheckpoints.checkpoint']));
    }

    public function demarrer(Request $request, Ronde $ronde, DemarrerRondeAction $action): RondeResource
    {
        $this->authorizeRondeAction($request, $ronde);

        return new RondeResource($action->execute($ronde->load('site')));
    }

    public function scanner(ScannerCheckpointRequest $request, Ronde $ronde, ScannerCheckpointAction $action): RondeResource
    {
        $this->authorizeRondeAction($request, $ronde);

        $data = $request->validated();

        return new RondeResource($action->execute(
            $ronde,
            $data['checkpoint_id'],
            isset($data['latitude']) ? (float) $data['latitude'] : null,
            isset($data['longitude']) ? (float) $data['longitude'] : null,
            $data['code_qr'] ?? null,
        ));
    }

    public function terminer(Request $request, Ronde $ronde, TerminerRondeAction $action): RondeResource
    {
        $this->authorizeRondeAction($request, $ronde);

        return new RondeResource($action->execute($ronde));
    }

    /**
     * Superviseurs (rondes.manage) can act on any ronde. Otherwise the authenticated
     * user must be the mobile agent linked to this ronde, authenticated via a
     * mobile-scoped token.
     */
    private function authorizeRondeAction(Request $request, Ronde $ronde): void
    {
        $user = $request->user();

        if ($user?->can('rondes.manage')) {
            return;
        }

        $isOwner = $user?->agent?->id !== null && $user->agent->id === $ronde->agent_id;

        abort_unless($isOwner && $user->tokenCan('mobile'), 403);
    }
}