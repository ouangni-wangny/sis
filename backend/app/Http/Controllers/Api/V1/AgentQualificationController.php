<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\QualificationResource;
use App\Models\Agent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/** Qualifications d’un agent (fiche agent) : elles lui donnent ses primes. */
class AgentQualificationController extends Controller
{
    public function index(Request $request, Agent $agent): AnonymousResourceCollection
    {
        abort_unless($request->user()?->can('agents.view') || $request->user()?->can('paie.view'), 403);

        return QualificationResource::collection($agent->qualifications()->orderBy('libelle')->get());
    }

    public function update(Request $request, Agent $agent): AnonymousResourceCollection
    {
        abort_unless($request->user()?->can('agents.update'), 403);

        $data = $request->validate([
            'qualification_ids' => ['present', 'array'],
            'qualification_ids.*' => ['uuid', Rule::exists('qualifications', 'id')->whereNull('deleted_at')],
        ]);

        $agent->qualifications()->sync(array_values(array_unique($data['qualification_ids'])));

        return QualificationResource::collection($agent->qualifications()->orderBy('libelle')->get());
    }
}
