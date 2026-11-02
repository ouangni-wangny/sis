<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Paie\AnnulerAvanceSalaireAction;
use App\Application\Paie\CreerAvanceSalaireAction;
use App\Domain\Paie\SituationSalaireMois;
use App\Domain\Shared\Enums\StatutAvanceSalaire;
use App\Http\Controllers\Controller;
use App\Http\Requests\Paie\StoreAvanceSalaireRequest;
use App\Http\Resources\AvanceSalaireResource;
use App\Models\Agent;
use App\Models\AvanceSalaire;
use App\Support\RechercheNom;
use App\Support\RhAuthorization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AvanceSalaireController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->autoriserLecture($request);

        $query = AvanceSalaire::query()
            ->when($request->filled('agent_id'), fn ($q) => $q->where('agent_id', $request->string('agent_id')))
            ->when($request->filled('mois'), fn ($q) => $q->where('mois', $request->integer('mois')))
            ->when($request->filled('annee'), fn ($q) => $q->where('annee', $request->integer('annee')))
            ->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->string('statut')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(function ($qq) use ($term) {
                    $qq->where('reference', 'like', $term)
                        ->orWhereHas('agent', function ($agent) use ($term) {
                            $agent->where('nom', 'like', $term)
                                ->orWhere('prenom', 'like', $term)
                                ->tap(fn ($a) => RechercheNom::ouNomComplet($a, $term))
                                ->orWhere('matricule', 'like', $term);
                        });
                });
            });

        $totalDonne = (clone $query)
            ->where('statut', '!=', StatutAvanceSalaire::Annulee->value)
            ->sum('montant');

        return AvanceSalaireResource::collection(
            $query->with(['agent', 'compte'])
                ->orderByDesc('date_avance')
                ->orderByDesc('created_at')
                ->paginate($request->integer('per_page', 15)),
        )->additional(['total_donne' => round((float) $totalDonne, 2)]);
    }

    public function situation(Request $request): JsonResponse
    {
        $this->autoriserLecture($request);

        $data = $request->validate([
            'agent_id' => ['required', 'uuid', 'exists:agents,id,deleted_at,NULL'],
            'mois' => ['required', 'integer', 'between:1,12'],
            'annee' => ['required', 'integer', 'between:2000,2100'],
        ]);

        return response()->json(['data' => SituationSalaireMois::pour(
            Agent::query()->findOrFail($data['agent_id']),
            (int) $data['mois'],
            (int) $data['annee'],
        )]);
    }

    public function store(StoreAvanceSalaireRequest $request, CreerAvanceSalaireAction $action): AvanceSalaireResource
    {
        abort_unless(RhAuthorization::canPayerPaie($request->user()), 403);

        return new AvanceSalaireResource($action->execute($request->validated())->load(['agent', 'compte']));
    }

    public function annuler(
        Request $request,
        AvanceSalaire $avanceSalaire,
        AnnulerAvanceSalaireAction $action,
    ): AvanceSalaireResource {
        abort_unless(RhAuthorization::canPayerPaie($request->user()), 403);

        $data = $request->validate(['motif' => ['nullable', 'string', 'max:1000']]);

        return new AvanceSalaireResource(
            $action->execute($avanceSalaire, $data['motif'] ?? null)->load(['agent', 'compte']),
        );
    }

    private function autoriserLecture(Request $request): void
    {
        abort_unless(
            RhAuthorization::canViewPaie($request->user()) && RhAuthorization::canSeeSalaire($request->user()),
            403,
        );
    }
}
