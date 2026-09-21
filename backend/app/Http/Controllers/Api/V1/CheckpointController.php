<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Shared\Enums\StatutRonde;
use App\Http\Controllers\Controller;
use App\Http\Requests\Checkpoint\StoreCheckpointRequest;
use App\Http\Resources\CheckpointResource;
use App\Models\Checkpoint;
use App\Models\RondeCheckpoint;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckpointController extends Controller
{
    public function index(Request $request, Site $site): AnonymousResourceCollection
    {
        abort_unless(
            $request->user()?->can('checkpoints.view')
                || $request->user()?->can('sites.view')
                || $request->user()?->can('rondes.view'),
            403,
        );

        return CheckpointResource::collection($site->checkpoints()->orderBy('ordre')->get());
    }

    public function store(StoreCheckpointRequest $request, Site $site): CheckpointResource
    {
        abort_unless(
            $request->user()?->can('checkpoints.manage') || $request->user()?->can('postes.manage'),
            403,
        );

        $data = $request->validated();
        $data['code_qr'] = $data['code_qr'] ?? ('QR-'.Str::upper(Str::random(10)));

        return new CheckpointResource($site->checkpoints()->create($data));
    }

    public function destroy(Request $request, Site $site, Checkpoint $checkpoint): Response
    {
        abort_unless(
            $request->user()?->can('checkpoints.manage') || $request->user()?->can('postes.manage'),
            403,
        );
        abort_unless($checkpoint->site_id === $site->id, 404);

        $usedInOpenRonde = RondeCheckpoint::query()
            ->where('checkpoint_id', $checkpoint->id)
            ->whereHas('ronde', fn ($q) => $q->where('statut', StatutRonde::EnCours))
            ->exists();

        if ($usedInOpenRonde) {
            throw ValidationException::withMessages([
                'checkpoint' => 'Impossible de supprimer : ce checkpoint est utilisé par une ronde en cours.',
            ]);
        }

        $checkpoint->delete();

        return response()->noContent();
    }
}
