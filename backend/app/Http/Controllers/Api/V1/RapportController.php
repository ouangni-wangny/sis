<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Reporting\EnqueueRapportAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rapport\StoreRapportRequest;
use App\Http\Resources\RapportExportResource;
use App\Models\RapportExport;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RapportController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()?->can('rapports.generate'), 403);

        $items = RapportExport::query()
            ->with('media')
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return RapportExportResource::collection($items);
    }

    public function store(StoreRapportRequest $request, string $type, EnqueueRapportAction $action): RapportExportResource
    {
        abort_unless($request->user()?->can('rapports.generate'), 403);

        $allowed = ['agents', 'plannings', 'pointages', 'controles', 'anomalies', 'rondes', 'contrats', 'absences', 'paie'];
        abort_unless(in_array($type, $allowed, true), 422, 'Type de rapport invalide.');

        $data = $request->validated();

        return new RapportExportResource($action->execute(
            $request->user(),
            $type,
            $data['format'],
            $data['filtres'],
        ));
    }

    public function show(Request $request, RapportExport $rapport): RapportExportResource
    {
        abort_unless($request->user()?->can('rapports.generate'), 403);

        return new RapportExportResource($rapport->load('media'));
    }

    public function download(Request $request, RapportExport $rapport): BinaryFileResponse
    {
        abort_unless($request->user()?->can('rapports.generate'), 403);

        $media = $rapport->getFirstMedia('export');
        abort_unless($media, 404, 'Fichier d’export indisponible.');

        $name = sprintf(
            'rapport-%s-%s.%s',
            $rapport->type,
            $rapport->created_at?->format('Ymd-His') ?? 'export',
            $rapport->format === 'xlsx' ? 'xlsx' : 'pdf',
        );

        return response()->download($media->getPath(), $name);
    }
}