<?php

$base = dirname(__DIR__);
function w(string $rel, string $c): void {
    global $base;
    $p = "$base/$rel";
    if (!is_dir(dirname($p))) mkdir(dirname($p), 0777, true);
    file_put_contents($p, $c);
    echo "OK $rel\n";
}

$controllers = [];

$controllers['VacationController'] = <<<'PHP'
<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Operation\CreateVacationAction;
use App\Application\Operation\DetecterConflitsVacationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vacation\StoreVacationRequest;
use App\Http\Resources\VacationResource;
use App\Models\Vacation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class VacationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Vacation::class);

        $items = Vacation::query()
            ->with(['agent', 'site', 'poste'])
            ->when($request->agent_id, fn ($q, $v) => $q->where('agent_id', $v))
            ->when($request->site_id, fn ($q, $v) => $q->where('site_id', $v))
            ->when($request->statut, fn ($q, $v) => $q->where('statut', $v))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return VacationResource::collection($items);
    }

    public function store(StoreVacationRequest $request, CreateVacationAction $action): VacationResource
    {
        $this->authorize('create', Vacation::class);

        return new VacationResource($action->execute($request->validated()));
    }

    public function show(Vacation $vacation): VacationResource
    {
        $this->authorize('view', $vacation);

        return new VacationResource($vacation->load(['agent', 'site', 'poste']));
    }

    public function destroy(Vacation $vacation): Response
    {
        $this->authorize('delete', $vacation);
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
}
PHP;

$controllers['RondeController'] = <<<'PHP'
<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Operation\DemarrerRondeAction;
use App\Application\Operation\ScannerCheckpointAction;
use App\Application\Operation\TerminerRondeAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ronde\ScannerCheckpointRequest;
use App\Http\Requests\Ronde\StoreRondeRequest;
use App\Http\Resources\RondeResource;
use App\Models\Ronde;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RondeController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $rondes = Ronde::query()
            ->with(['agent', 'site'])
            ->when($request->statut, fn ($q, $v) => $q->where('statut', $v))
            ->when($request->agent_id, fn ($q, $v) => $q->where('agent_id', $v))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return RondeResource::collection($rondes);
    }

    public function store(StoreRondeRequest $request): RondeResource
    {
        $ronde = Ronde::query()->create($request->validated());

        return new RondeResource($ronde->load(['agent', 'site']));
    }

    public function show(Ronde $ronde): RondeResource
    {
        return new RondeResource($ronde->load(['agent', 'site', 'rondeCheckpoints.checkpoint']));
    }

    public function demarrer(Ronde $ronde, DemarrerRondeAction $action): RondeResource
    {
        return new RondeResource($action->execute($ronde->load('site')));
    }

    public function scanner(ScannerCheckpointRequest $request, Ronde $ronde, ScannerCheckpointAction $action): RondeResource
    {
        $data = $request->validated();

        return new RondeResource($action->execute(
            $ronde,
            $data['checkpoint_id'],
            isset($data['latitude']) ? (float) $data['latitude'] : null,
            isset($data['longitude']) ? (float) $data['longitude'] : null,
            $data['code_qr'] ?? null,
        ));
    }

    public function terminer(Ronde $ronde, TerminerRondeAction $action): RondeResource
    {
        return new RondeResource($action->execute($ronde));
    }
}
PHP;

$controllers['ControleController'] = <<<'PHP'
<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Operation\EnregistrerControleAction;
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
        $items = Controle::query()
            ->with(['media', 'agent', 'site'])
            ->when($request->site_id, fn ($q, $v) => $q->where('site_id', $v))
            ->latest('effectue_at')
            ->paginate($request->integer('per_page', 15));

        return ControleResource::collection($items);
    }

    public function store(StoreControleRequest $request, EnregistrerControleAction $action): ControleResource
    {
        $photos = $request->file('photos', []);
        if (! is_array($photos)) {
            $photos = $photos ? [$photos] : [];
        }

        return new ControleResource($action->execute($request->safe()->except('photos'), $photos));
    }

    public function show(Controle $controle): ControleResource
    {
        return new ControleResource($controle->load(['media', 'agent', 'site']));
    }
}
PHP;

$controllers['AnomalieController'] = <<<'PHP'
<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Operation\SignalerAnomalieAction;
use App\Application\Operation\UpdateAnomalieStatutAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Anomalie\StoreAnomalieRequest;
use App\Http\Requests\Anomalie\UpdateAnomalieStatutRequest;
use App\Http\Resources\AnomalieResource;
use App\Models\Anomalie;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AnomalieController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Anomalie::class);

        $items = Anomalie::query()
            ->with(['media', 'site', 'signalePar'])
            ->when($request->statut, fn ($q, $v) => $q->where('statut', $v))
            ->when($request->gravite, fn ($q, $v) => $q->where('gravite', $v))
            ->when($request->type, fn ($q, $v) => $q->where('type', $v))
            ->latest('signale_at')
            ->paginate($request->integer('per_page', 15));

        return AnomalieResource::collection($items);
    }

    public function store(StoreAnomalieRequest $request, SignalerAnomalieAction $action): AnomalieResource
    {
        $this->authorize('create', Anomalie::class);
        $photos = $request->file('photos', []);
        if (! is_array($photos)) {
            $photos = $photos ? [$photos] : [];
        }

        return new AnomalieResource($action->execute($request->safe()->except('photos'), $photos));
    }

    public function show(Anomalie $anomalie): AnomalieResource
    {
        $this->authorize('view', $anomalie);

        return new AnomalieResource($anomalie->load(['media', 'site', 'signalePar', 'assigneA']));
    }

    public function updateStatut(UpdateAnomalieStatutRequest $request, Anomalie $anomalie, UpdateAnomalieStatutAction $action): AnomalieResource
    {
        $this->authorize('update', $anomalie);
        $data = $request->validated();

        return new AnomalieResource($action->execute($anomalie, $data['statut'], $data['assigne_a_id'] ?? null));
    }
}
PHP;

$controllers['ContratController'] = <<<'PHP'
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contrat\StoreContratRequest;
use App\Http\Resources\ContratResource;
use App\Models\Contrat;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ContratController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $items = Contrat::query()
            ->when($request->agent_id, fn ($q, $v) => $q->where('agent_id', $v))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return ContratResource::collection($items);
    }

    public function store(StoreContratRequest $request): ContratResource
    {
        $contrat = Contrat::query()->create($request->validated());
        if ($request->hasFile('document')) {
            $contrat->addMediaFromRequest('document')->toMediaCollection('document');
        }

        return new ContratResource($contrat->load('media'));
    }

    public function show(Contrat $contrat): ContratResource
    {
        return new ContratResource($contrat->load('media'));
    }

    public function destroy(Contrat $contrat): Response
    {
        $contrat->delete();

        return response()->noContent();
    }
}
PHP;

$controllers['AbsenceController'] = <<<'PHP'
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Absence\StoreAbsenceRequest;
use App\Http\Requests\Absence\UpdateAbsenceRequest;
use App\Http\Resources\AbsenceResource;
use App\Models\Absence;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class AbsenceController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $items = Absence::query()
            ->when($request->agent_id, fn ($q, $v) => $q->where('agent_id', $v))
            ->when($request->statut, fn ($q, $v) => $q->where('statut', $v))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return AbsenceResource::collection($items);
    }

    public function store(StoreAbsenceRequest $request): AbsenceResource
    {
        return new AbsenceResource(Absence::query()->create($request->validated()));
    }

    public function update(UpdateAbsenceRequest $request, Absence $absence): AbsenceResource
    {
        $absence->update($request->validated());

        return new AbsenceResource($absence);
    }

    public function destroy(Absence $absence): Response
    {
        $absence->delete();

        return response()->noContent();
    }
}
PHP;

$controllers['OffreController'] = <<<'PHP'
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Offre\StoreOffreRequest;
use App\Http\Requests\Offre\UpdateOffreRequest;
use App\Http\Resources\OffreResource;
use App\Models\Offre;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class OffreController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return OffreResource::collection(Offre::query()->latest()->paginate($request->integer('per_page', 15)));
    }

    public function store(StoreOffreRequest $request): OffreResource
    {
        return new OffreResource(Offre::query()->create($request->validated()));
    }

    public function show(Offre $offre): OffreResource
    {
        return new OffreResource($offre);
    }

    public function update(UpdateOffreRequest $request, Offre $offre): OffreResource
    {
        $offre->update($request->validated());

        return new OffreResource($offre);
    }

    public function destroy(Offre $offre): Response
    {
        $offre->delete();

        return response()->noContent();
    }
}
PHP;

$controllers['AbonnementController'] = <<<'PHP'
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Abonnement\StoreAbonnementRequest;
use App\Http\Resources\AbonnementResource;
use App\Models\Abonnement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class AbonnementController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $items = Abonnement::query()
            ->when($request->client_id, fn ($q, $v) => $q->where('client_id', $v))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return AbonnementResource::collection($items);
    }

    public function store(StoreAbonnementRequest $request): AbonnementResource
    {
        return new AbonnementResource(Abonnement::query()->create($request->validated()));
    }

    public function show(Abonnement $abonnement): AbonnementResource
    {
        return new AbonnementResource($abonnement);
    }

    public function destroy(Abonnement $abonnement): Response
    {
        $abonnement->delete();

        return response()->noContent();
    }
}
PHP;

$controllers['FactureController'] = <<<'PHP'
<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Commercial\GenererFactureDepuisVacationsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Facture\GenererFactureRequest;
use App\Http\Resources\FactureResource;
use App\Jobs\GenererFacturePdfJob;
use App\Models\Facture;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FactureController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Facture::class);

        $items = Facture::query()
            ->with(['client', 'media'])
            ->when($request->client_id, fn ($q, $v) => $q->where('client_id', $v))
            ->when($request->statut, fn ($q, $v) => $q->where('statut', $v))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return FactureResource::collection($items);
    }

    public function show(Facture $facture): FactureResource
    {
        $this->authorize('view', $facture);

        return new FactureResource($facture->load(['client', 'lignes', 'media']));
    }

    public function generer(GenererFactureRequest $request, GenererFactureDepuisVacationsAction $action): FactureResource
    {
        $this->authorize('create', Facture::class);
        $data = $request->validated();

        return new FactureResource($action->execute($data['client_id'], $data['date_debut'], $data['date_fin']));
    }

    public function genererPdf(Facture $facture): FactureResource
    {
        $this->authorize('update', $facture);
        GenererFacturePdfJob::dispatchSync($facture->id);

        return new FactureResource($facture->fresh()->load(['media', 'lignes', 'client']));
    }
}
PHP;

$controllers['DashboardController'] = <<<'PHP'
<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Reporting\GetDashboardStatsAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function stats(Request $request, GetDashboardStatsAction $action): JsonResponse
    {
        $stats = $action->execute(
            $request->input('from'),
            $request->input('to'),
            $request->input('zone_id'),
            $request->input('client_id'),
        );

        return response()->json(['data' => $stats]);
    }
}
PHP;

$controllers['RapportController'] = <<<'PHP'
<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Reporting\EnqueueRapportAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rapport\StoreRapportRequest;
use App\Http\Resources\RapportExportResource;
use App\Models\RapportExport;
use Illuminate\Http\Request;

class RapportController extends Controller
{
    public function store(StoreRapportRequest $request, string $type, EnqueueRapportAction $action): RapportExportResource
    {
        $data = $request->validated();

        return new RapportExportResource($action->execute(
            $request->user(),
            $type,
            $data['format'] ?? 'pdf',
            $data['filtres'] ?? [],
        ));
    }

    public function show(RapportExport $rapport): RapportExportResource
    {
        return new RapportExportResource($rapport->load('media'));
    }
}
PHP;

$controllers['UserController'] = <<<'PHP'
<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Shared\Enums\StatutUser;
use App\Domain\Shared\Enums\TypeUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()
            ->with('roles')
            ->where('type', TypeUser::Backoffice)
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return UserResource::collection($users);
    }

    public function store(StoreUserRequest $request): UserResource
    {
        $this->authorize('create', User::class);
        $data = $request->validated();
        $role = $data['role'] ?? 'superviseur';
        unset($data['role']);

        $user = User::query()->create([
            ...$data,
            'name' => trim(($data['prenom'] ?? '').' '.($data['nom'] ?? '')),
            'password' => Hash::make($data['password']),
            'type' => TypeUser::Backoffice,
            'statut' => $data['statut'] ?? StatutUser::Actif->value,
        ]);
        $user->assignRole($role);

        return new UserResource($user->load('roles'));
    }

    public function show(User $user): UserResource
    {
        $this->authorize('view', $user);

        return new UserResource($user->load(['roles', 'permissions']));
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $this->authorize('update', $user);
        $data = $request->validated();
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }
        if (isset($data['role'])) {
            $user->syncRoles([$data['role']]);
            unset($data['role']);
        }
        $user->update($data);

        return new UserResource($user->load('roles'));
    }

    public function destroy(User $user): Response
    {
        $this->authorize('delete', $user);
        $user->delete();

        return response()->noContent();
    }
}
PHP;

$controllers['AuditController'] = <<<'PHP'
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\JournalAuditResource;
use App\Models\JournalAudit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AuditController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $items = JournalAudit::query()
            ->with('user')
            ->when($request->action, fn ($q, $v) => $q->where('action', $v))
            ->when($request->auditable_type, fn ($q, $v) => $q->where('auditable_type', $v))
            ->latest()
            ->paginate($request->integer('per_page', 30));

        return JournalAuditResource::collection($items);
    }
}
PHP;

$controllers['MobileSyncController'] = <<<'PHP'
<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Operation\EnregistrerControleAction;
use App\Application\Operation\SignalerAnomalieAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\MobileSyncRequest;
use App\Http\Resources\AnomalieResource;
use App\Http\Resources\ControleResource;
use Illuminate\Http\JsonResponse;

class MobileSyncController extends Controller
{
    public function sync(
        MobileSyncRequest $request,
        EnregistrerControleAction $controleAction,
        SignalerAnomalieAction $anomalieAction,
    ): JsonResponse {
        $data = $request->validated();
        $controles = [];
        $anomalies = [];

        foreach ($data['controles'] ?? [] as $item) {
            $controles[] = new ControleResource($controleAction->execute($item));
        }

        foreach ($data['anomalies'] ?? [] as $item) {
            $anomalies[] = new AnomalieResource($anomalieAction->execute($item));
        }

        return response()->json([
            'data' => [
                'controles' => $controles,
                'anomalies' => $anomalies,
            ],
        ]);
    }
}
PHP;

foreach ($controllers as $name => $content) {
    w("app/Http/Controllers/Api/V1/{$name}.php", $content);
}

echo "All controllers done\n";
