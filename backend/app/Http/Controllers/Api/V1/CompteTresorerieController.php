<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Tresorerie\CreateAjustementTresorerieAction;
use App\Application\Tresorerie\CreateTransfertTresorerieAction;
use App\Application\Tresorerie\GetTresorerieStatsAction;
use App\Domain\Shared\Enums\TypeCompteTresorerie;
use App\Http\Controllers\Controller;
use App\Http\Resources\CompteTresorerieResource;
use App\Http\Resources\MouvementTresorerieResource;
use App\Models\CompteTresorerie;
use App\Models\MouvementTresorerie;
use App\Support\ModePaiementRules;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class CompteTresorerieController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', CompteTresorerie::class);

        $items = CompteTresorerie::query()
            ->when($request->boolean('actif_only'), fn ($q) => $q->where('actif', true))
            ->orderBy('libelle')
            ->get();

        return CompteTresorerieResource::collection($items);
    }

    public function store(Request $request): CompteTresorerieResource
    {
        $this->authorize('create', CompteTresorerie::class);

        $data = $request->validate([
            'libelle' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(TypeCompteTresorerie::class)],
            'solde_ouverture' => ['nullable', 'numeric'],
            'actif' => ['sometimes', 'boolean'],
        ]);

        $compte = CompteTresorerie::query()->create([
            'libelle' => $data['libelle'],
            'type' => $data['type'],
            'solde_ouverture' => $data['solde_ouverture'] ?? 0,
            'actif' => $data['actif'] ?? true,
        ]);

        return new CompteTresorerieResource($compte);
    }

    public function update(Request $request, CompteTresorerie $compteTresorerie): CompteTresorerieResource
    {
        $this->authorize('update', $compteTresorerie);

        $data = $request->validate([
            'libelle' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', Rule::enum(TypeCompteTresorerie::class)],
            'solde_ouverture' => ['sometimes', 'numeric'],
            'actif' => ['sometimes', 'boolean'],
        ]);

        $compteTresorerie->update($data);

        return new CompteTresorerieResource($compteTresorerie->fresh());
    }

    public function destroy(Request $request, CompteTresorerie $compteTresorerie): \Illuminate\Http\Response
    {
        $this->authorize('delete', $compteTresorerie);

        $used = MouvementTresorerie::query()
            ->where('compte_tresorerie_id', $compteTresorerie->id)
            ->exists();

        if ($used) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'compte' => 'Ce compte a des mouvements. Désactivez-le plutôt.',
            ]);
        }

        $compteTresorerie->delete();

        return response()->noContent();
    }

    public function mouvements(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', CompteTresorerie::class);

        $items = MouvementTresorerie::query()
            ->with('compte')
            ->when($request->filled('compte_id'), fn ($q) => $q->where('compte_tresorerie_id', $request->string('compte_id')))
            ->when($request->filled('direction'), fn ($q) => $q->where('direction', $request->string('direction')))
            ->when($request->filled('source_type'), fn ($q) => $q->where('source_type', $request->string('source_type')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('date_mouvement', '>=', $request->string('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('date_mouvement', '<=', $request->string('to')))
            ->latest('date_mouvement')
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return MouvementTresorerieResource::collection($items);
    }

    public function ajustement(Request $request, CreateAjustementTresorerieAction $action): MouvementTresorerieResource
    {
        $this->authorize('create', CompteTresorerie::class);

        $data = $request->validate([
            'compte_tresorerie_id' => ['required', 'uuid', 'exists:comptes_tresorerie,id'],
            'direction' => ['required', Rule::in(['entree', 'sortie'])],
            'montant' => ['required', 'numeric', 'min:0.01'],
            'date_mouvement' => ['required', 'date'],
            'mode' => ModePaiementRules::sometimes(),
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        return new MouvementTresorerieResource($action->execute($data)->load('compte'));
    }

    public function transfert(Request $request, CreateTransfertTresorerieAction $action): \Illuminate\Http\JsonResponse
    {
        $this->authorize('create', CompteTresorerie::class);

        $data = $request->validate([
            'compte_source_id' => ['required', 'uuid', Rule::exists('comptes_tresorerie', 'id')->where('actif', true)->whereNull('deleted_at')],
            'compte_destination_id' => ['required', 'uuid', 'different:compte_source_id', Rule::exists('comptes_tresorerie', 'id')->where('actif', true)->whereNull('deleted_at')],
            'montant' => ['required', 'numeric', 'min:0.01'],
            'date_mouvement' => ['required', 'date'],
            'mode' => ModePaiementRules::sometimes(),
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'compte_destination_id.different' => 'Le compte destination doit être différent du compte source.',
            'compte_source_id.exists' => 'Le compte source est introuvable ou inactif.',
            'compte_destination_id.exists' => 'Le compte destination est introuvable ou inactif.',
        ]);

        $result = $action->execute($data);

        return response()->json([
            'data' => [
                'sortie' => new MouvementTresorerieResource($result['sortie']->load('compte')),
                'entree' => new MouvementTresorerieResource($result['entree']->load('compte')),
            ],
        ], 201);
    }

    public function stats(Request $request, GetTresorerieStatsAction $action): \Illuminate\Http\JsonResponse
    {
        $this->authorize('viewAny', CompteTresorerie::class);

        $data = $request->validate([
            'mois' => ['nullable', 'integer', 'min:1', 'max:12'],
            'annee' => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ]);

        return response()->json([
            'data' => $action->execute(
                isset($data['mois']) ? (int) $data['mois'] : null,
                isset($data['annee']) ? (int) $data['annee'] : null,
            ),
        ]);
    }
}
