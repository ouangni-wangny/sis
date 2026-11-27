<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Qualification\QualificationRequest;
use App\Http\Resources\QualificationResource;
use App\Models\Qualification;
use App\Support\ListQuery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class QualificationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless(
            $request->user()?->can('grades.manage')
            || $request->user()?->can('paie.view')
            || $request->user()?->can('agents.view'),
            403,
        );

        $items = Qualification::query()
            ->withCount('primes')
            ->when($request->boolean('actif_only'), fn ($q) => $q->where('actif', true))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q')->toString().'%';
                $q->where(fn ($inner) => $inner->where('libelle', 'like', $term)->orWhere('abreviation', 'like', $term));
            })
            ->orderBy('libelle');

        return QualificationResource::collection(ListQuery::paginateOrAll($items, $request));
    }

    public function store(QualificationRequest $request): QualificationResource
    {
        return new QualificationResource(Qualification::query()->create($request->validated()));
    }

    public function update(QualificationRequest $request, Qualification $qualification): QualificationResource
    {
        $qualification->update($request->validated());

        return new QualificationResource($qualification);
    }

    public function destroy(Request $request, Qualification $qualification): Response
    {
        abort_unless($request->user()?->can('grades.manage'), 403);

        if ($qualification->primes()->exists()) {
            throw ValidationException::withMessages([
                'qualification' => 'Cette qualification a des primes. Supprimez-les d’abord, ou désactivez la qualification.',
            ]);
        }

        $qualification->delete();

        return response()->noContent();
    }
}
