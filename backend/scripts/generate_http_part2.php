<?php

$base = dirname(__DIR__);
function w(string $rel, string $c): void {
    global $base;
    $p = "$base/$rel";
    if (!is_dir(dirname($p))) mkdir(dirname($p), 0777, true);
    file_put_contents($p, $c);
    echo "OK $rel\n";
}

// Policies
foreach (['Client', 'Agent', 'Site', 'Vacation', 'Anomalie', 'Facture', 'User'] as $model) {
    $permPrefix = strtolower($model).'s';
    if ($model === 'Anomalie') $permPrefix = 'anomalies';
    if ($model === 'Vacation') $permPrefix = 'vacations';
    if ($model === 'Facture') $permPrefix = 'factures';
    if ($model === 'User') $permPrefix = 'users';
    if ($model === 'Site') $permPrefix = 'sites';
    if ($model === 'Agent') $permPrefix = 'agents';
    if ($model === 'Client') $permPrefix = 'clients';

    w("app/Policies/{$model}Policy.php", <<<PHP
<?php

namespace App\\Policies;

use App\\Models\\{$model};
use App\\Models\\User;

class {$model}Policy
{
    public function viewAny(User \$user): bool
    {
        return \$user->can('{$permPrefix}.view') || \$user->hasRole('super-admin');
    }

    public function view(User \$user, {$model} \$model): bool
    {
        return \$user->can('{$permPrefix}.view') || \$user->hasRole('super-admin');
    }

    public function create(User \$user): bool
    {
        return \$user->can('{$permPrefix}.create') || \$user->hasRole('super-admin');
    }

    public function update(User \$user, {$model} \$model): bool
    {
        return \$user->can('{$permPrefix}.update') || \$user->hasRole('super-admin');
    }

    public function delete(User \$user, {$model} \$model): bool
    {
        return \$user->can('{$permPrefix}.delete') || \$user->hasRole('super-admin');
    }
}

PHP);
}

// Controllers - Auth
w('app/Http/Controllers/Api/V1/AuthController.php', <<<'PHP'
<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Identity\LoginWebAction;
use App\Application\Identity\RevokeDeviceTokenAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function login(LoginRequest $request, LoginWebAction $action): JsonResponse
    {
        $result = $action->execute(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
            $request->string('device_name', 'web')->toString(),
        );

        return response()->json([
            'data' => [
                'token' => $result['token'],
                'user' => new UserResource($result['user']->load('roles')),
            ],
        ]);
    }

    public function logout(Request $request, RevokeDeviceTokenAction $action): JsonResponse
    {
        $action->execute($request->user());

        return response()->json(['message' => 'Déconnecté.']);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user()->load(['roles', 'permissions']));
    }
}

PHP);

w('app/Http/Controllers/Api/V1/MobileAuthController.php', <<<'PHP'
<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Identity\LoginMobileAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\MobileLoginRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;

class MobileAuthController extends Controller
{
    public function login(MobileLoginRequest $request, LoginMobileAction $action): JsonResponse
    {
        $result = $action->execute(
            $request->string('matricule')->toString(),
            $request->string('pin')->toString(),
            $request->string('device_name', 'mobile')->toString(),
        );

        return response()->json([
            'data' => [
                'token' => $result['token'],
                'user' => new UserResource($result['user']),
            ],
        ]);
    }
}

PHP);

w('app/Http/Controllers/Api/V1/ClientController.php', <<<'PHP'
<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Referentiel\CreateClientAction;
use App\Application\Referentiel\UpdateClientAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\StoreClientRequest;
use App\Http\Requests\Client\UpdateClientRequest;
use App\Http\Resources\ClientResource;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ClientController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Client::class);

        $clients = Client::query()
            ->withCount('sites')
            ->when($request->q, fn ($q, $v) => $q->where('raison_sociale', 'like', "%{$v}%"))
            ->when($request->type, fn ($q, $v) => $q->where('type', $v))
            ->when($request->statut, fn ($q, $v) => $q->where('statut', $v))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return ClientResource::collection($clients);
    }

    public function store(StoreClientRequest $request, CreateClientAction $action): ClientResource
    {
        $this->authorize('create', Client::class);

        return new ClientResource($action->execute($request->validated()));
    }

    public function show(Client $client): ClientResource
    {
        $this->authorize('view', $client);

        return new ClientResource($client->loadCount('sites'));
    }

    public function update(UpdateClientRequest $request, Client $client, UpdateClientAction $action): ClientResource
    {
        $this->authorize('update', $client);

        return new ClientResource($action->execute($client, $request->validated()));
    }

    public function destroy(Client $client): Response
    {
        $this->authorize('delete', $client);
        $client->delete();

        return response()->noContent();
    }
}

PHP);

w('app/Http/Controllers/Api/V1/ZoneController.php', <<<'PHP'
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Zone\StoreZoneRequest;
use App\Http\Requests\Zone\UpdateZoneRequest;
use App\Http\Resources\ZoneResource;
use App\Models\Zone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ZoneController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $zones = Zone::query()->withCount('sites')->latest()->paginate($request->integer('per_page', 15));

        return ZoneResource::collection($zones);
    }

    public function store(StoreZoneRequest $request): ZoneResource
    {
        return new ZoneResource(Zone::query()->create($request->validated()));
    }

    public function show(Zone $zone): ZoneResource
    {
        return new ZoneResource($zone->loadCount('sites'));
    }

    public function update(UpdateZoneRequest $request, Zone $zone): ZoneResource
    {
        $zone->update($request->validated());

        return new ZoneResource($zone);
    }

    public function destroy(Zone $zone): Response
    {
        $zone->delete();

        return response()->noContent();
    }
}

PHP);

w('app/Http/Controllers/Api/V1/GradeController.php', <<<'PHP'
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Grade\StoreGradeRequest;
use App\Http\Requests\Grade\UpdateGradeRequest;
use App\Http\Resources\GradeResource;
use App\Models\Grade;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class GradeController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return GradeResource::collection(Grade::query()->latest()->paginate($request->integer('per_page', 15)));
    }

    public function store(StoreGradeRequest $request): GradeResource
    {
        return new GradeResource(Grade::query()->create($request->validated()));
    }

    public function show(Grade $grade): GradeResource
    {
        return new GradeResource($grade);
    }

    public function update(UpdateGradeRequest $request, Grade $grade): GradeResource
    {
        $grade->update($request->validated());

        return new GradeResource($grade);
    }

    public function destroy(Grade $grade): Response
    {
        $grade->delete();

        return response()->noContent();
    }
}

PHP);

w('app/Http/Controllers/Api/V1/SiteController.php', <<<'PHP'
<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Site\CreateSiteWithPostesAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Site\StoreSiteRequest;
use App\Http\Requests\Site\UpdateSiteRequest;
use App\Http\Resources\SiteResource;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class SiteController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Site::class);

        $sites = Site::query()
            ->with(['client', 'zone'])
            ->when($request->client_id, fn ($q, $v) => $q->where('client_id', $v))
            ->when($request->zone_id, fn ($q, $v) => $q->where('zone_id', $v))
            ->when($request->q, fn ($q, $v) => $q->where('nom', 'like', "%{$v}%"))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return SiteResource::collection($sites);
    }

    public function store(StoreSiteRequest $request, CreateSiteWithPostesAction $action): SiteResource
    {
        $this->authorize('create', Site::class);
        $data = $request->validated();
        $postes = $data['postes'] ?? [];
        unset($data['postes']);

        return new SiteResource($action->execute($data, $postes));
    }

    public function show(Site $site): SiteResource
    {
        $this->authorize('view', $site);

        return new SiteResource($site->load(['client', 'zone', 'postes', 'checkpoints']));
    }

    public function update(UpdateSiteRequest $request, Site $site): SiteResource
    {
        $this->authorize('update', $site);
        $site->update($request->validated());

        return new SiteResource($site->load(['client', 'zone', 'postes']));
    }

    public function destroy(Site $site): Response
    {
        $this->authorize('delete', $site);
        $site->delete();

        return response()->noContent();
    }
}

PHP);

w('app/Http/Controllers/Api/V1/PosteController.php', <<<'PHP'
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Poste\StorePosteRequest;
use App\Http\Resources\PosteResource;
use App\Models\Poste;
use App\Models\Site;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class PosteController extends Controller
{
    public function index(Site $site): AnonymousResourceCollection
    {
        return PosteResource::collection($site->postes()->latest()->get());
    }

    public function store(StorePosteRequest $request, Site $site): PosteResource
    {
        $poste = $site->postes()->create($request->validated());

        return new PosteResource($poste);
    }

    public function destroy(Site $site, Poste $poste): Response
    {
        abort_unless($poste->site_id === $site->id, 404);
        $poste->delete();

        return response()->noContent();
    }
}

PHP);

w('app/Http/Controllers/Api/V1/CheckpointController.php', <<<'PHP'
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Checkpoint\StoreCheckpointRequest;
use App\Http\Resources\CheckpointResource;
use App\Models\Checkpoint;
use App\Models\Site;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class CheckpointController extends Controller
{
    public function index(Site $site): AnonymousResourceCollection
    {
        return CheckpointResource::collection($site->checkpoints()->orderBy('ordre')->get());
    }

    public function store(StoreCheckpointRequest $request, Site $site): CheckpointResource
    {
        $data = $request->validated();
        $data['code_qr'] = $data['code_qr'] ?? ('QR-'.Str::upper(Str::random(10)));

        return new CheckpointResource($site->checkpoints()->create($data));
    }

    public function destroy(Site $site, Checkpoint $checkpoint): Response
    {
        abort_unless($checkpoint->site_id === $site->id, 404);
        $checkpoint->delete();

        return response()->noContent();
    }
}

PHP);

w('app/Http/Controllers/Api/V1/AgentController.php', <<<'PHP'
<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Agent\CreateAgentWithMobileAccountAction;
use App\Application\Agent\SyncRondierPerimetreAction;
use App\Application\Agent\UploadAgentPhotoAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\StoreAgentRequest;
use App\Http\Requests\Agent\SyncPerimetreRequest;
use App\Http\Requests\Agent\UpdateAgentRequest;
use App\Http\Requests\Agent\UploadAgentPhotoRequest;
use App\Http\Resources\AgentResource;
use App\Models\Agent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class AgentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Agent::class);

        $agents = Agent::query()
            ->with(['grade', 'media'])
            ->when($request->type, fn ($q, $v) => $q->where('type', $v))
            ->when($request->statut, fn ($q, $v) => $q->where('statut', $v))
            ->when($request->q, fn ($q, $v) => $q->where(fn ($qq) => $qq
                ->where('nom', 'like', "%{$v}%")
                ->orWhere('prenom', 'like', "%{$v}%")
                ->orWhere('matricule', 'like', "%{$v}%")))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return AgentResource::collection($agents);
    }

    public function store(StoreAgentRequest $request, CreateAgentWithMobileAccountAction $action): AgentResource
    {
        $this->authorize('create', Agent::class);

        return new AgentResource($action->execute($request->validated()));
    }

    public function show(Agent $agent): AgentResource
    {
        $this->authorize('view', $agent);

        return new AgentResource($agent->load(['grade', 'media', 'user']));
    }

    public function update(UpdateAgentRequest $request, Agent $agent): AgentResource
    {
        $this->authorize('update', $agent);
        $agent->update($request->validated());

        return new AgentResource($agent->load(['grade', 'media']));
    }

    public function destroy(Agent $agent): Response
    {
        $this->authorize('delete', $agent);
        $agent->delete();

        return response()->noContent();
    }

    public function storePhoto(UploadAgentPhotoRequest $request, Agent $agent, UploadAgentPhotoAction $action): AgentResource
    {
        $this->authorize('update', $agent);

        return new AgentResource($action->execute($agent, $request->file('photo')));
    }

    public function syncPerimetre(SyncPerimetreRequest $request, Agent $agent, SyncRondierPerimetreAction $action): AgentResource
    {
        $this->authorize('update', $agent);

        return new AgentResource($action->execute($agent, $request->validated('items')));
    }
}

PHP);

w('app/Http/Controllers/Api/V1/PerimetreController.php', <<<'PHP'
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AgentResource;
use App\Models\Agent;
use App\Domain\Shared\Enums\TypeAgent;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PerimetreController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $rondiers = Agent::query()
            ->where('type', TypeAgent::Rondier)
            ->with(['perimetres.zone', 'perimetres.site', 'grade'])
            ->latest()
            ->get();

        return AgentResource::collection($rondiers);
    }
}

PHP);

echo "Controllers batch 1 done\n";
