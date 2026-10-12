<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Operation\CreateBulkVacationsAction;
use App\Application\Operation\CreateVacationAction;
use App\Application\Operation\DecouperPeriodeVacation;
use App\Application\Operation\DetecterConflitsVacationAction;
use App\Application\Operation\NormalizeVacationSchedule;
use App\Application\Operation\RattacherRondesApresDecoupage;
use App\Application\Operation\RecouvrirVacationAction;
use App\Application\Operation\UpdateVacationAction;
use App\Application\Reporting\PointageVacationsAction;
use App\Application\Shared\RondierPerimetreGuard;
use App\Domain\Shared\Enums\StatutRonde;
use App\Domain\Shared\Enums\StatutVacation;
use App\Domain\Shared\Enums\TypeAgent;
use App\Domain\Shared\Support\ShiftSchedule;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vacation\RecouvrirVacationRequest;
use App\Http\Requests\Vacation\StoreBulkVacationsRequest;
use App\Http\Requests\Vacation\StoreVacationRequest;
use App\Http\Requests\Vacation\UpdateVacationRequest;
use App\Http\Resources\VacationResource;
use App\Models\Ronde;
use App\Models\Vacation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VacationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Vacation::class);

        // Un contrôleur ne voit que les agents en poste dans son périmètre assigné.
        $controleurAgent = $request->user()?->agent?->type === TypeAgent::Controleur
            ? $request->user()->agent
            : null;

        $items = Vacation::query()
            ->with(['agent', 'site', 'poste'])
            ->when($controleurAgent, function ($q) use ($controleurAgent) {
                $q->whereIn('site_id', RondierPerimetreGuard::authorizedSiteIds($controleurAgent));
            })
            ->when($request->agent_id, fn ($q, $v) => $q->where('agent_id', $v))
            ->when($request->site_id, fn ($q, $v) => $q->where('site_id', $v))
            ->when($request->poste_id, fn ($q, $v) => $q->where('poste_id', $v))
            ->when($request->statut, fn ($q, $v) => $q->where('statut', $v))
            // Vacations qui touchent la période [date_from, date_to] (grille semaine).
            ->when($request->date('date_from'), function ($q, $from) {
                $q->where(function ($inner) use ($from) {
                    $inner->whereNull('date_fin')->orWhereDate('date_fin', '>=', $from->format('Y-m-d'));
                });
            })
            ->when($request->date('date_to'), fn ($q, $to) => $q->whereDate('date_debut', '<=', $to->format('Y-m-d')))
            ->when($request->boolean('en_poste'), function ($q) use ($request) {
                $date = $request->date('date')?->format('Y-m-d') ?? now()->toDateString();
                $q->whereIn('statut', [StatutVacation::Planifiee, StatutVacation::EnCours])
                    ->whereDate('date_debut', '<=', $date)
                    ->where(function ($inner) use ($date) {
                        $inner->whereNull('date_fin')->orWhereDate('date_fin', '>=', $date);
                    })
                    ->whereHas('agent', fn ($a) => $a->where('type', TypeAgent::Agent));
            })
            ->when($request->has('site_interne'), function ($q) use ($request) {
                $q->whereHas('site', fn ($s) => $s->where('interne', $request->boolean('site_interne')));
            })
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q')->toString().'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('statut', 'like', $term)
                        ->orWhereHas('agent', function ($a) use ($term) {
                            $a->where('nom', 'like', $term)
                                ->orWhere('prenom', 'like', $term)
                                ->orWhere('matricule', 'like', $term);
                        })
                        ->orWhereHas('site', fn ($s) => $s->where('nom', 'like', $term))
                        ->orWhereHas('poste', fn ($p) => $p->where('nom', 'like', $term));
                });
            })
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return VacationResource::collection($items);
    }

    public function store(
        StoreVacationRequest $request,
        CreateVacationAction $action,
        CreateBulkVacationsAction $bulk,
    ): VacationResource {
        $this->authorize('create', Vacation::class);

        $rows = DecouperPeriodeVacation::rows($request->validated());
        if (count($rows) === 1) {
            return new VacationResource($action->execute($rows[0]));
        }

        // Période : une vacation par jour (tout ou rien).
        $result = $bulk->execute([
            'vacations' => $rows,
            'annuler_conflits' => (bool) $request->boolean('annuler_conflits'),
        ]);

        return (new VacationResource($result['vacations'][0]))
            ->additional(['meta' => ['created' => $result['created']]]);
    }

    public function storeBulk(
        StoreBulkVacationsRequest $request,
        CreateBulkVacationsAction $action,
    ): JsonResponse {
        $this->authorize('create', Vacation::class);

        $result = $action->execute($request->validated());

        return response()->json([
            'data' => [
                'created' => $result['created'],
                'removed' => $result['removed'],
            ],
        ], 201);
    }

    public function show(Vacation $vacation): VacationResource
    {
        $this->authorize('view', $vacation);

        return new VacationResource($vacation->load(['agent', 'site', 'poste']));
    }

    public function update(
        UpdateVacationRequest $request,
        Vacation $vacation,
        UpdateVacationAction $action,
        CreateVacationAction $create,
    ): VacationResource {
        $this->authorize('update', $vacation);
        $data = $request->validated();

        // Étendue à une période : comme à la création, une vacation par jour
        // de prise de poste (date_fin en sémantique stockée : nuit → lendemain).
        $jours = $this->joursApresModification($vacation, $data);
        if (count($jours) <= 1) {
            return new VacationResource($action->execute($vacation, $data));
        }

        $premier = array_shift($jours);
        $updated = DB::transaction(function () use ($vacation, $data, $premier, $jours, $action, $create) {
            $updated = $action->execute($vacation, [
                ...$data,
                'date_debut' => $premier,
                'date_fin' => $premier,
            ]);

            $morceaux = [$updated];
            foreach ($jours as $jour) {
                $morceaux[] = $create->execute([
                    'agent_id' => $updated->agent_id,
                    'site_id' => $updated->site_id,
                    'poste_id' => $updated->poste_id,
                    'heure_debut' => substr((string) $updated->heure_debut, 0, 5),
                    'heure_fin' => substr((string) $updated->heure_fin, 0, 5),
                    'date_debut' => $jour,
                    'date_fin' => $jour,
                ], wrapInTransaction: false);
            }
            RattacherRondesApresDecoupage::execute($updated, $morceaux, $updated);

            return $updated;
        });

        return (new VacationResource($updated))
            ->additional(['meta' => ['created' => count($jours)]]);
    }

    /**
     * Jours de prise de poste après modification (vide si sans fin).
     *
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function joursApresModification(Vacation $vacation, array $data): array
    {
        // Changement de statut seul : on ne touche pas au découpage existant.
        if (array_intersect(['date_debut', 'date_fin', 'heure_debut', 'heure_fin'], array_keys($data)) === []) {
            return [];
        }

        $dateFin = array_key_exists('date_fin', $data)
            ? $data['date_fin']
            : $vacation->date_fin?->format('Y-m-d');
        if ($dateFin === null || $dateFin === '') {
            return [];
        }

        $schedule = NormalizeVacationSchedule::apply(
            $data['date_debut'] ?? $vacation->date_debut->format('Y-m-d'),
            $data['heure_debut'] ?? (string) $vacation->heure_debut,
            $data['heure_fin'] ?? (string) $vacation->heure_fin,
            $dateFin,
        );

        $jours = ShiftSchedule::fromStored(
            $schedule['date_debut'],
            $schedule['heure_debut'],
            $schedule['heure_fin'],
            $schedule['date_fin'],
        )->days();

        if (count($jours) > DecouperPeriodeVacation::MAX_JOURS) {
            throw ValidationException::withMessages([
                'date_fin' => sprintf('Période trop longue (maximum %d jours).', DecouperPeriodeVacation::MAX_JOURS),
            ]);
        }

        return $jours;
    }

    public function destroy(Vacation $vacation): Response
    {
        $this->authorize('delete', $vacation);

        $hasOpenRonde = Ronde::query()
            ->where('vacation_id', $vacation->id)
            ->whereIn('statut', [StatutRonde::Planifiee, StatutRonde::EnCours])
            ->exists();

        if ($hasOpenRonde) {
            throw ValidationException::withMessages([
                'vacation' => 'Impossible de supprimer : une ronde liée est encore planifiée ou en cours.',
            ]);
        }

        $vacation->delete();

        return response()->noContent();
    }

    public function conflits(Request $request, DetecterConflitsVacationAction $action): JsonResponse
    {
        $request->validate([
            'agent_id' => ['required', 'uuid'],
            'date_debut' => ['required', 'date'],
            'date_fin' => ['nullable', 'date'],
            'heure_debut' => ['required', 'date_format:H:i'],
            'heure_fin' => ['required', 'date_format:H:i'],
        ]);

        $conflits = $action->execute(
            $request->string('agent_id')->toString(),
            $request->string('date_debut')->toString(),
            $request->string('heure_debut')->toString(),
            $request->string('heure_fin')->toString(),
            $request->input('date_fin'),
        );

        return response()->json([
            'data' => VacationResource::collection($conflits),
            'meta' => ['count' => $conflits->count()],
        ]);
    }

    /**
     * Pointage par créneau (contrôle présent / absent / non contrôlé) et heures
     * comptées : seuls les créneaux contrôlés présents comptent des heures.
     */
    public function pointage(Request $request, PointageVacationsAction $action): JsonResponse
    {
        $this->authorize('viewAny', Vacation::class);

        $data = $request->validate([
            'agent_id' => ['nullable', 'uuid'],
            'site_id' => ['nullable', 'uuid'],
            'date_debut' => ['required', 'date_format:Y-m-d'],
            'date_fin' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_debut'],
        ]);

        // Même périmètre que la liste : un contrôleur ne voit que ses sites.
        $controleurAgent = $request->user()?->agent?->type === TypeAgent::Controleur
            ? $request->user()->agent
            : null;

        $vacations = Vacation::query()
            ->with(['agent:id,nom,prenom,matricule', 'site:id,nom', 'poste:id,nom'])
            ->when($controleurAgent, function ($q) use ($controleurAgent) {
                $q->whereIn('site_id', RondierPerimetreGuard::authorizedSiteIds($controleurAgent));
            })
            ->when($data['agent_id'] ?? null, fn ($q, $v) => $q->where('agent_id', $v))
            ->when($data['site_id'] ?? null, fn ($q, $v) => $q->where('site_id', $v))
            ->whereDate('date_debut', '<=', $data['date_fin'])
            ->where(function ($q) use ($data) {
                $q->whereNull('date_fin')->orWhereDate('date_fin', '>=', $data['date_debut']);
            })
            ->get();

        $lignes = $action->execute($vacations, $data['date_debut'], $data['date_fin']);

        return response()->json([
            'data' => array_map(fn (array $l) => [
                'date' => $l['date'],
                'vacation_id' => (string) $l['vacation']->id,
                'agent_id' => (string) $l['vacation']->agent_id,
                'agent' => $l['vacation']->agent
                    ? trim($l['vacation']->agent->prenom.' '.$l['vacation']->agent->nom).' ('.$l['vacation']->agent->matricule.')'
                    : null,
                'statut' => $l['vacation']->statut?->value,
                'site' => $l['vacation']->site?->nom,
                'poste' => $l['vacation']->poste?->nom,
                'heure_debut' => substr((string) $l['vacation']->heure_debut, 0, 5),
                'heure_fin' => substr((string) $l['vacation']->heure_fin, 0, 5),
                'controle' => $l['controle'],
                'heures_prevues' => $l['heures_prevues'],
                'heures_comptees' => $l['heures_comptees'],
            ], $lignes),
            'meta' => PointageVacationsAction::totaux($lignes),
        ]);
    }

    public function recouvrir(
        RecouvrirVacationRequest $request,
        Vacation $vacation,
        RecouvrirVacationAction $action,
    ): VacationResource {
        $this->authorize('update', $vacation);

        return new VacationResource(
            $action->execute($vacation, $request->string('agent_id')->toString())
        );
    }
}