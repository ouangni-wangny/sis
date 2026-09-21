<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Paie\GenerateBulletinsPaieAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\PeriodePaieResource;
use App\Models\PeriodePaie;
use App\Support\RhAuthorization;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PeriodePaieController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless(RhAuthorization::canViewPaie($request->user()), 403);

        return PeriodePaieResource::collection(
            PeriodePaie::query()
                ->withCount('bulletins')
                ->when($request->filled('annee'), fn ($q) => $q->where('annee', $request->integer('annee')))
                ->when($request->filled('mois'), fn ($q) => $q->where('mois', $request->integer('mois')))
                ->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->string('statut')))
                ->when($request->filled('q'), function ($q) use ($request) {
                    $term = '%'.$request->string('q').'%';
                    $q->where(function ($qq) use ($term) {
                        $qq->where('commentaire', 'like', $term)
                            ->orWhere('statut', 'like', $term)
                            ->orWhereRaw("CONCAT(mois, '/', annee) like ?", [$term])
                            ->orWhereRaw("CONCAT(LPAD(mois, 2, '0'), '/', annee) like ?", [$term]);
                    });
                })
                ->orderByDesc('annee')
                ->orderByDesc('mois')
                ->paginate($request->integer('per_page', 15)),
        );
    }

    public function store(Request $request): PeriodePaieResource
    {
        abort_unless(RhAuthorization::canManagePaie($request->user()), 403);

        $data = $request->validate([
            'mois' => ['required', 'integer', 'min:1', 'max:12'],
            'annee' => ['required', 'integer', 'min:2000', 'max:2100'],
            'commentaire' => ['nullable', 'string'],
        ]);

        $debut = now()->setDate($data['annee'], $data['mois'], 1)->startOfMonth();
        $fin = $debut->copy()->endOfMonth();

        $periode = PeriodePaie::query()->create([
            ...$data,
            'date_debut' => $debut->toDateString(),
            'date_fin' => $fin->toDateString(),
            'statut' => 'brouillon',
        ]);

        return new PeriodePaieResource($periode);
    }

    public function show(Request $request, PeriodePaie $periodePaie): PeriodePaieResource
    {
        abort_unless(RhAuthorization::canViewPaie($request->user()), 403);

        return new PeriodePaieResource($periodePaie->loadCount('bulletins'));
    }

    public function genererBulletins(
        Request $request,
        PeriodePaie $periodePaie,
        GenerateBulletinsPaieAction $action,
    ): PeriodePaieResource {
        abort_unless(RhAuthorization::canManagePaie($request->user()), 403);

        $action->execute($periodePaie);

        return new PeriodePaieResource($periodePaie->fresh()->loadCount('bulletins'));
    }

    public function bulletins(Request $request, PeriodePaie $periodePaie): AnonymousResourceCollection
    {
        abort_unless(RhAuthorization::canViewPaie($request->user()), 403);

        $items = $periodePaie->bulletins()
            ->with('agent')
            ->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->string('statut')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(function ($qq) use ($term) {
                    $qq->where('statut', 'like', $term)
                        ->orWhereHas('agent', function ($agent) use ($term) {
                            $agent->where('nom', 'like', $term)
                                ->orWhere('prenom', 'like', $term)
                                ->orWhere('matricule', 'like', $term);
                        });
                });
            })
            ->paginate($request->integer('per_page', 50));

        return \App\Http\Resources\BulletinPaieResource::collection($items);
    }

    public function valider(Request $request, PeriodePaie $periodePaie): PeriodePaieResource
    {
        abort_unless(RhAuthorization::canManagePaie($request->user()), 403);

        $periodePaie->update(['statut' => 'validee']);

        return new PeriodePaieResource($periodePaie);
    }

    public function cloturer(Request $request, PeriodePaie $periodePaie): PeriodePaieResource
    {
        abort_unless(RhAuthorization::canManagePaie($request->user()), 403);

        $periodePaie->update(['statut' => 'cloturee']);

        return new PeriodePaieResource($periodePaie);
    }
}
