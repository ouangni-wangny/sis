<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Operation\EnregistrerControleAction;
use App\Application\Shared\RondierPerimetreGuard;
use App\Domain\Shared\Enums\TypeAgent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Controle\StoreControleRequest;
use App\Http\Resources\ControleResource;
use App\Models\Controle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ControleController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()?->can('controles.view'), 403);

        // Un contrôleur ne consulte que les contrôles de son périmètre assigné.
        $controleurAgent = $request->user()?->agent?->type === TypeAgent::Controleur
            ? $request->user()->agent
            : null;

        $items = Controle::query()
            ->with(['media', 'agent', 'controleAgent', 'site', 'poste', 'enregistrePar'])
            ->when($controleurAgent, function ($q) use ($controleurAgent) {
                $q->whereIn('site_id', RondierPerimetreGuard::authorizedSiteIds($controleurAgent));
            })
            ->when($request->site_id, fn ($q, $v) => $q->where('site_id', $v))
            ->when($request->controle_agent_id, fn ($q, $v) => $q->where('controle_agent_id', $v))
            ->when($request->boolean('aujourd_hui'), function ($q) {
                $q->whereDate('effectue_at', now()->toDateString());
            })
            ->when($request->boolean('mes_controles'), function ($q) use ($request) {
                $agentId = $request->user()?->agent?->id;
                if ($agentId) {
                    $q->where('agent_id', $agentId);
                }
            })
            ->when($request->filled('resultat'), function ($q) use ($request) {
                $resultat = $request->string('resultat')->toString();
                if ($resultat === 'enregistre') {
                    $q->whereNull('resultat');
                } else {
                    $q->where('resultat', $resultat);
                }
            })
            ->when($request->boolean('recent_30j'), fn ($q) => $q->whereDate('effectue_at', '>=', now()->subDays(30)->toDateString()))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q')->toString().'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('commentaire', 'like', $term)
                        ->orWhereHas('controleAgent', function ($agent) use ($term) {
                            $agent->where('nom', 'like', $term)
                                ->orWhere('prenom', 'like', $term)
                                ->orWhere('matricule', 'like', $term);
                        })
                        ->orWhereHas('agent', function ($rondier) use ($term) {
                            $rondier->where('nom', 'like', $term)
                                ->orWhere('prenom', 'like', $term)
                                ->orWhere('matricule', 'like', $term);
                        })
                        ->orWhereHas('enregistrePar', function ($user) use ($term) {
                            $user->where('nom', 'like', $term)
                                ->orWhere('prenom', 'like', $term)
                                ->orWhere('email', 'like', $term);
                        })
                        ->orWhereHas('site', function ($site) use ($term) {
                            $site->where('nom', 'like', $term);
                        })
                        ->orWhereHas('poste', function ($poste) use ($term) {
                            $poste->where('nom', 'like', $term);
                        });
                });
            })
            ->latest('effectue_at')
            ->paginate($request->integer('per_page', 15));

        return ControleResource::collection($items);
    }

    public function store(StoreControleRequest $request, EnregistrerControleAction $action): ControleResource
    {
        abort_unless($request->user()?->can('controles.create'), 403);

        $actingAgent = $request->user()?->agent;
        if ($actingAgent?->type === TypeAgent::Controleur) {
            abort_unless($actingAgent->id === $request->input('agent_id'), 403);
        }

        $photos = $request->file('photos', []);
        if (! is_array($photos)) {
            $photos = $photos ? [$photos] : [];
        }
        $photos = array_values(array_filter($photos));

        if ($photos === [] && $request->hasFile('photos.0')) {
            $photos = [$request->file('photos.0')];
        }
        if ($photos === [] && $request->hasFile('photo')) {
            $photos = [$request->file('photo')];
        }

        $base64Photos = [];
        if ($request->filled('photo_base64')) {
            $base64Photos[] = (string) $request->input('photo_base64');
        }

        $payload = $request->safe()->except(['photos', 'photo', 'photo_base64']);
        $isSiege = $request->isSiegeOperation();
        if ($isSiege) {
            $payload['enregistre_par_user_id'] = $request->user()?->id;
            if (empty($payload['agent_id'])) {
                $payload['agent_id'] = null;
            }
        }

        return new ControleResource(
            $action->execute(
                $payload,
                $photos,
                requirePhoto: ! $isSiege,
                base64Photos: $base64Photos,
            )
        );
    }

    public function show(Request $request, Controle $controle): ControleResource
    {
        abort_unless($request->user()?->can('controles.view'), 403);

        return new ControleResource(
            $controle->load(['media', 'agent', 'controleAgent', 'site', 'poste', 'enregistrePar'])
        );
    }
}
