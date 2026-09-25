<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Contrat\GenerateContratReferenceAction;
use App\Application\Contrat\GenererListeContratsPdfAction;
use App\Application\Contrat\HandleContratActivationAction;
use App\Application\Contrat\HandleContratClotureAction;
use App\Application\Contrat\SyncContratRemunerationAction;
use App\Application\Rh\GetContratsAlertsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Contrat\StoreContratRequest;
use App\Http\Requests\Contrat\UpdateContratRequest;
use App\Http\Resources\ContratResource;
use App\Models\Contrat;
use App\Support\RhAuthorization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ContratController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless(RhAuthorization::canViewContrats($request->user()), 403);

        $items = GenererListeContratsPdfAction::filteredQuery($request)
            ->with(['agent', 'media', 'contratParent'])
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return ContratResource::collection($items);
    }

    public function exportPdf(Request $request, GenererListeContratsPdfAction $action): SymfonyResponse
    {
        abort_unless(RhAuthorization::canViewContrats($request->user()), 403);

        return $action->execute($request);
    }

    public function alerts(Request $request, GetContratsAlertsAction $action): JsonResponse
    {
        abort_unless(RhAuthorization::canViewContrats($request->user()), 403);

        return response()->json(['data' => $action->execute()]);
    }

    public function store(
        StoreContratRequest $request,
        SyncContratRemunerationAction $syncRemuneration,
        GenerateContratReferenceAction $generateReference,
        HandleContratActivationAction $activation,
    ): ContratResource {
        abort_unless(RhAuthorization::canManageContrats($request->user()), 403);

        $data = collect($request->validated())->except('document')->all();
        if (blank($data['reference'] ?? null)) {
            $data['reference'] = $generateReference->execute(
                (string) $data['type'],
                isset($data['date_debut'])
                    ? now()->parse((string) $data['date_debut'])->timezone('Africa/Abidjan')->format('Y')
                    : null,
            );
        }
        $data = $syncRemuneration->prepare($data);

        $contrat = Contrat::query()->create($data);
        if ($request->hasFile('document')) {
            $contrat->addMediaFromRequest('document')->toMediaCollection('document');
        }

        $activation->execute($contrat->refresh());

        return new ContratResource($contrat->load(['media', 'agent']));
    }

    public function storeAvenant(
        StoreContratRequest $request,
        Contrat $contrat,
        SyncContratRemunerationAction $syncRemuneration,
        GenerateContratReferenceAction $generateReference,
        HandleContratActivationAction $activation,
    ): ContratResource {
        abort_unless(RhAuthorization::canManageContrats($request->user()), 403);

        $data = collect($request->validated())->except('document')->all();
        $data['agent_id'] = $contrat->agent_id;
        $data['contrat_parent_id'] = $contrat->id;

        if (blank($data['reference'] ?? null)) {
            $data['reference'] = $generateReference->execute(
                (string) $data['type'],
                isset($data['date_debut'])
                    ? now()->parse((string) $data['date_debut'])->timezone('Africa/Abidjan')->format('Y')
                    : null,
            ).'-AV';
        }

        $data = $syncRemuneration->prepare($data, $contrat->agent, $contrat);
        $avenant = Contrat::query()->create($data);

        if ($request->hasFile('document')) {
            $avenant->addMediaFromRequest('document')->toMediaCollection('document');
        }

        $activation->execute($avenant->refresh());

        return new ContratResource($avenant->load(['media', 'agent', 'contratParent']));
    }

    public function show(Request $request, Contrat $contrat): ContratResource
    {
        abort_unless(RhAuthorization::canViewContrats($request->user()), 403);

        return new ContratResource($contrat->load(['media', 'agent', 'contratParent']));
    }

    public function update(
        UpdateContratRequest $request,
        Contrat $contrat,
        SyncContratRemunerationAction $syncRemuneration,
        HandleContratClotureAction $cloture,
        HandleContratActivationAction $activation,
    ): ContratResource {
        abort_unless(RhAuthorization::canManageContrats($request->user()), 403);

        $previousStatut = $contrat->statut;

        $data = $syncRemuneration->prepare(
            $request->validated(),
            $contrat->agent,
            $contrat,
        );

        $contrat->update($data);

        $fresh = $contrat->fresh();
        $cloture->execute($fresh, $previousStatut);
        $activation->execute($fresh);

        if ($request->hasFile('document')) {
            $contrat->clearMediaCollection('document');
            $contrat->addMediaFromRequest('document')->toMediaCollection('document');
        }

        return new ContratResource($contrat->fresh()->load(['media', 'agent']));
    }

    public function downloadDocument(Request $request, Contrat $contrat): BinaryFileResponse
    {
        abort_unless(RhAuthorization::canViewContrats($request->user()), 403);

        $media = $contrat->getFirstMedia('document');
        abort_unless($media, 404);

        return response()->download($media->getPath(), $media->file_name);
    }

    public function destroy(Request $request, Contrat $contrat): Response
    {
        abort_unless(RhAuthorization::canManageContrats($request->user()), 403);

        if ($contrat->statut === 'actif') {
            throw ValidationException::withMessages([
                'statut' => 'Impossible de supprimer un contrat actif. Passez-le en terminé ou résilié d’abord.',
            ]);
        }

        $contrat->delete();

        return response()->noContent();
    }
}
