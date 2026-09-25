<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Paie\GenerateBulletinsPaieAction;
use App\Application\Paie\GenererListeBulletinsPdfAction;
use App\Domain\Shared\Enums\StatutPeriodePaie;
use App\Http\Controllers\Controller;
use App\Http\Resources\PeriodePaieResource;
use App\Models\PeriodePaie;
use App\Support\ListQuery;
use App\Support\RhAuthorization;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

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

        $items = GenererListeBulletinsPdfAction::filteredQuery($request, $periodePaie)
            ->with(['agent.grade', 'media'])
            ->orderBy('created_at');

        return \App\Http\Resources\BulletinPaieResource::collection(
            ListQuery::paginateOrAll($items, $request, 50),
        );
    }

    public function exportBulletinsPdf(
        Request $request,
        PeriodePaie $periodePaie,
        GenererListeBulletinsPdfAction $action,
    ): SymfonyResponse {
        abort_unless(RhAuthorization::canViewPaie($request->user()), 403);

        return $action->execute($request, $periodePaie);
    }

    public function valider(Request $request, PeriodePaie $periodePaie): PeriodePaieResource
    {
        abort_unless(RhAuthorization::canManagePaie($request->user()), 403);

        $periodePaie->update(['statut' => 'validee']);
        $periodePaie->bulletins()
            ->where('statut', 'brouillon')
            ->update(['statut' => 'valide']);

        return new PeriodePaieResource($periodePaie->fresh()->loadCount('bulletins'));
    }

    public function cloturer(Request $request, PeriodePaie $periodePaie): PeriodePaieResource
    {
        abort_unless(RhAuthorization::canManagePaie($request->user()), 403);

        $periodePaie->update(['statut' => 'cloturee']);

        return new PeriodePaieResource($periodePaie);
    }

    public function destroy(Request $request, PeriodePaie $periodePaie): Response
    {
        abort_unless(RhAuthorization::canManagePaie($request->user()), 403);

        if ($periodePaie->statut !== StatutPeriodePaie::Brouillon) {
            throw ValidationException::withMessages([
                'periode' => 'Seules les périodes en brouillon peuvent être supprimées.',
            ]);
        }

        if ($periodePaie->bulletins()->exists()) {
            throw ValidationException::withMessages([
                'periode' => 'Cette période a des bulletins. Supprimez-les ou conservez la période.',
            ]);
        }

        $periodePaie->delete();

        return response()->noContent();
    }
}
