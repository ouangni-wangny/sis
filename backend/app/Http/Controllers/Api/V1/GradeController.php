<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Shared\Enums\TypeAgent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Grade\StoreGradeRequest;
use App\Http\Requests\Grade\UpdateGradeRequest;
use App\Http\Resources\GradeResource;
use App\Models\Grade;
use App\Support\ListQuery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class GradeController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless(
            $request->user()?->can('grades.manage') || $request->user()?->can('agents.view'),
            403,
        );

        $grades = Grade::query()
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q')->toString().'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('libelle', 'like', $term)
                        ->orWhere('description', 'like', $term)
                        ->orWhere('type_agent', 'like', $term);
                });
            })
            ->latest();

        return GradeResource::collection(ListQuery::paginateOrAll($grades, $request));
    }

    public function store(StoreGradeRequest $request): GradeResource
    {
        abort_unless($request->user()?->can('grades.manage'), 403);

        return new GradeResource(Grade::query()->create(self::normaliser($request->validated())));
    }

    public function show(Request $request, Grade $grade): GradeResource
    {
        abort_unless(
            $request->user()?->can('grades.manage') || $request->user()?->can('agents.view'),
            403,
        );

        return new GradeResource($grade);
    }

    public function update(UpdateGradeRequest $request, Grade $grade): GradeResource
    {
        abort_unless($request->user()?->can('grades.manage'), 403);

        $grade->update(self::normaliser($request->validated(), $grade));

        return new GradeResource($grade);
    }

    public function destroy(Request $request, Grade $grade): Response
    {
        abort_unless($request->user()?->can('grades.manage'), 403);

        $grade->delete();

        return response()->noContent();
    }

    /**
     * Seul un grade de type « agent » peut être un grade de rondier (agent de
     * relève qui tient des postes).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function normaliser(array $data, ?Grade $grade = null): array
    {
        $type = $data['type_agent'] ?? $grade?->type_agent;
        $type = $type instanceof TypeAgent ? $type : TypeAgent::tryFrom((string) $type);
        if ($type !== TypeAgent::Agent) {
            $data['est_rondier'] = false;
        }

        return $data;
    }
}
