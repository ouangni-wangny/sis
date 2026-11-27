<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Qualification\PrimeQualificationRequest;
use App\Http\Resources\PrimeQualificationResource;
use App\Models\PrimeQualification;
use App\Support\ListQuery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class PrimeQualificationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()?->can('grades.manage') || $request->user()?->can('paie.view'), 403);

        $items = PrimeQualification::query()
            ->with('qualification')
            ->join('qualifications', 'qualifications.id', '=', 'primes_qualification.qualification_id')
            ->select('primes_qualification.*')
            ->when($request->filled('qualification_id'), fn ($q) => $q->where('qualification_id', $request->string('qualification_id')->toString()))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q')->toString().'%';
                $q->where(fn ($inner) => $inner->where('qualifications.libelle', 'like', $term)
                    ->orWhere('qualifications.abreviation', 'like', $term));
            })
            ->orderBy('qualifications.libelle')
            ->orderBy('primes_qualification.unite');

        return PrimeQualificationResource::collection(ListQuery::paginateOrAll($items, $request));
    }

    public function store(PrimeQualificationRequest $request): PrimeQualificationResource
    {
        $bareme = PrimeQualification::query()->create($request->validated());

        return new PrimeQualificationResource($bareme->load('qualification'));
    }

    public function update(PrimeQualificationRequest $request, PrimeQualification $primeQualification): PrimeQualificationResource
    {
        $primeQualification->update($request->validated());

        return new PrimeQualificationResource($primeQualification->load('qualification'));
    }

    public function destroy(Request $request, PrimeQualification $primeQualification): Response
    {
        abort_unless($request->user()?->can('grades.manage'), 403);

        $primeQualification->delete();

        return response()->noContent();
    }
}
