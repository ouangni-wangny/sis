<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Shared\RondierPerimetreGuard;
use App\Http\Controllers\Controller;
use App\Http\Resources\AgentResource;
use App\Models\Agent;
use App\Domain\Shared\Enums\TypeAgent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PerimetreController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless(
            $request->user()?->can('perimetres.view') || $request->user()?->can('agents.view'),
            403,
        );

        $rondiers = Agent::query()
            ->where('type', TypeAgent::Controleur)
            ->avecContratValide()
            ->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->string('statut')))
            ->when($request->filled('zone_id'), fn ($q) => $q->whereHas(
                'perimetres',
                fn ($p) => $p->where('zone_id', $request->string('zone_id')),
            ))
            ->when($request->boolean('sans_zone'), fn ($q) => $q->whereDoesntHave('perimetres'))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q')->toString().'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('nom', 'like', $term)
                        ->orWhere('prenom', 'like', $term)
                        ->orWhere('matricule', 'like', $term)
                        ->orWhereHas('grade', fn ($g) => $g->where('libelle', 'like', $term))
                        ->orWhereHas('perimetres.zone', fn ($z) => $z->where('nom', 'like', $term));
                });
            })
            ->with(['perimetres.zone', 'grade'])
            ->latest()
            ->get();

        // Charge courante : nombre de sites du périmètre + agents postés actuellement dessus.
        $rondiers->each(function (Agent $rondier) {
            $rondier->perimetre_sites_count = count(RondierPerimetreGuard::authorizedSiteIds($rondier));
            $rondier->perimetre_agents_count = count(RondierPerimetreGuard::activeAgentIds($rondier));
        });

        return AgentResource::collection($rondiers);
    }
}
