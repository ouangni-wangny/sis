<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Referentiel\CreateClientAction;
use App\Application\Referentiel\SoftDeleteClientCascadeAction;
use App\Application\Referentiel\UpdateClientAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\StoreClientRequest;
use App\Http\Requests\Client\UpdateClientRequest;
use App\Http\Resources\ClientResource;
use App\Models\Client;
use App\Support\ListQuery;
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
            ->latest();

        return ClientResource::collection(ListQuery::paginateOrAll($clients, $request));
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

    public function destroy(Client $client, SoftDeleteClientCascadeAction $action): Response
    {
        $this->authorize('delete', $client);
        $action->execute($client);

        return response()->noContent();
    }
}
