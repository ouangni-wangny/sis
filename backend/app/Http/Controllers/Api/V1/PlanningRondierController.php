<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Planning\AffecterRondierAction;
use App\Application\Planning\PlanifierSemaineRondierAction;
use App\Application\Planning\RetirerRondierAction;
use App\Application\Planning\TrousRondiersQuery;
use App\Domain\Shared\Enums\StatutAgent;
use App\Domain\Shared\Enums\StatutVacation;
use App\Domain\Shared\Enums\TypeAgent;
use App\Http\Controllers\Controller;
use App\Http\Resources\VacationResource;
use App\Models\Agent;
use App\Models\Vacation;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

/**
 * Planning des rondiers (agents de relève) : leur semaine sur tous les sites
 * et les trous à combler (repos des titulaires, places vides, absences).
 */
class PlanningRondierController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Vacation::class);
        // Outil du siège : il expose les téléphones des rondiers et les postes
        // dégarnis de tous les sites — jamais aux comptes terrain (agent posté,
        // contrôleur), même s’ils ont vacations.view pour leur propre usage.
        abort_if(
            in_array($request->user()?->agent?->type, [TypeAgent::Agent, TypeAgent::Controleur], true),
            403,
            'Le planning des rondiers est réservé au siège.',
        );

        $data = $request->validate([
            'lundi' => ['required', 'date_format:Y-m-d'],
            'site_id' => ['nullable', 'uuid'],
            'zone_id' => ['nullable', 'uuid'],
        ]);

        $lundi = Carbon::parse($data['lundi'])->startOfWeek(Carbon::MONDAY);
        $dimanche = $lundi->copy()->addDays(6);

        $rondiers = Agent::query()
            ->rondiers()
            ->whereNotIn('statut', [StatutAgent::Archive->value])
            ->with(['vacations' => fn ($q) => $q
                ->with(['site:id,nom', 'poste:id,nom'])
                ->where('statut', '!=', StatutVacation::Annulee->value)
                // Vacations qui touchent la semaine (y compris sur période).
                ->whereDate('date_debut', '<=', $dimanche->toDateString())
                ->where(fn ($p) => $p->whereNull('date_fin')
                    ->orWhereDate('date_fin', '>=', $lundi->copy()->subDay()->toDateString()))
                ->orderBy('date_debut')
                ->orderBy('heure_debut'),
            ])
            ->orderBy('nom')
            ->orderBy('prenom')
            ->get(['id', 'nom', 'prenom', 'matricule', 'telephone', 'statut', 'grade_id']);

        return response()->json([
            'data' => [
                'lundi' => $lundi->toDateString(),
                'dimanche' => $dimanche->toDateString(),
                'rondiers' => $rondiers->map(fn (Agent $a) => [
                    'id' => (string) $a->id,
                    'nom' => $a->nom,
                    'prenom' => $a->prenom,
                    'matricule' => $a->matricule,
                    'telephone' => $a->telephone,
                    'statut' => $a->statut,
                    'vacations' => $a->vacations->map(fn (Vacation $v) => [
                        'id' => (string) $v->id,
                        'date_debut' => $v->date_debut->toDateString(),
                        'date_fin' => $v->date_fin?->toDateString(),
                        'heure_debut' => substr((string) $v->heure_debut, 0, 5),
                        'heure_fin' => substr((string) $v->heure_fin, 0, 5),
                        'statut' => $v->statut,
                        'est_remplacement' => (bool) $v->est_remplacement,
                        'site' => $v->site ? ['id' => (string) $v->site->id, 'nom' => $v->site->nom] : null,
                        'poste' => $v->poste ? ['id' => (string) $v->poste->id, 'nom' => $v->poste->nom] : null,
                    ])->values(),
                ])->values(),
                'trous' => TrousRondiersQuery::semaine(
                    $lundi->toDateString(),
                    $data['site_id'] ?? null,
                    $data['zone_id'] ?? null,
                ),
            ],
        ]);
    }

    public function affecter(Request $request, AffecterRondierAction $action): VacationResource
    {
        $this->authorize('create', Vacation::class);

        $data = $request->validate([
            'agent_id' => ['required', 'uuid', 'exists:agents,id'],
            'poste_id' => ['required', 'uuid', 'exists:postes,id'],
            'date' => ['required', 'date_format:Y-m-d'],
            'quart' => ['nullable', 'in:jour,nuit'],
        ]);

        return new VacationResource($action->execute(
            $data['agent_id'],
            $data['poste_id'],
            $data['date'],
            $data['quart'] ?? null,
        ));
    }

    /** Semaine d’un rondier en une fois (formulaire « Planifier un rondier »). */
    public function planifier(Request $request, PlanifierSemaineRondierAction $action): JsonResponse
    {
        $this->authorize('create', Vacation::class);
        // Replanifier une semaine peut retirer des affectations : droit de suppression requis.
        abort_unless(
            $request->user()?->can('vacations.delete') || $request->user()?->hasRole('super-admin'),
            403,
            'Vous n’avez pas le droit de retirer des vacations.',
        );

        $data = $request->validate([
            'agent_id' => ['required', 'uuid', 'exists:agents,id'],
            'jours' => ['required', 'array', 'min:1', 'max:7'],
            'jours.*.date' => ['required', 'date_format:Y-m-d', 'distinct'],
            'jours.*.poste_id' => ['nullable', 'uuid', 'exists:postes,id'],
            'jours.*.quart' => ['nullable', 'in:jour,nuit'],
        ]);

        // Une seule semaine ISO à la fois.
        $semaines = collect($data['jours'])
            ->map(fn (array $j) => Carbon::parse($j['date'])->startOfWeek(Carbon::MONDAY)->toDateString())
            ->unique();
        if ($semaines->count() > 1) {
            throw ValidationException::withMessages([
                'jours' => 'Les jours envoyés doivent appartenir à une même semaine (lundi → dimanche).',
            ]);
        }

        return response()->json(['data' => $action->execute($data['agent_id'], $data['jours'])]);
    }

    public function retirer(Vacation $vacation, RetirerRondierAction $action): Response
    {
        $this->authorize('delete', $vacation);

        $action->execute($vacation);

        return response()->noContent();
    }
}
