<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Paie\GenererBulletinPaiePdfAction;
use App\Application\Paie\RenseignerSalairePercuAction;
use App\Application\Paie\RenseignerSalairePercuBulkAction;
use App\Application\Tresorerie\ReglerBulletinPaieAction;
use App\Application\Tresorerie\ReglerBulletinPaieBulkAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Paie\ReglerBulletinPaieBulkRequest;
use App\Http\Requests\Paie\ReglerBulletinPaieRequest;
use App\Http\Requests\Paie\RenseignerSalairePercuBulkRequest;
use App\Http\Requests\Paie\RenseignerSalairePercuRequest;
use App\Http\Resources\BulletinPaieResource;
use App\Models\BulletinPaie;
use App\Support\RhAuthorization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BulletinPaieController extends Controller
{
    public function show(Request $request, BulletinPaie $bulletinPaie): BulletinPaieResource
    {
        abort_unless(RhAuthorization::canViewPaie($request->user()), 403);

        return new BulletinPaieResource($bulletinPaie->load(['agent', 'contrat', 'periodePaie', 'compteTresorerie', 'media']));
    }

    public function genererPdf(
        Request $request,
        BulletinPaie $bulletinPaie,
        GenererBulletinPaiePdfAction $action,
    ): BulletinPaieResource {
        abort_unless(RhAuthorization::canSeeSalaire($request->user()), 403);
        abort_unless(RhAuthorization::canManagePaie($request->user()), 403);

        return new BulletinPaieResource($action->execute($bulletinPaie)->load('media'));
    }

    public function downloadPdf(Request $request, BulletinPaie $bulletinPaie): BinaryFileResponse
    {
        abort_unless(RhAuthorization::canSeeSalaire($request->user()), 403);

        $media = $bulletinPaie->getFirstMedia('pdf');
        abort_unless($media, 404);

        return response()->download(
            $media->getPath(),
            "bulletin-{$bulletinPaie->id}.pdf",
        );
    }

    public function renseignerSalaire(
        RenseignerSalairePercuRequest $request,
        BulletinPaie $bulletinPaie,
        RenseignerSalairePercuAction $action,
    ): BulletinPaieResource {
        abort_unless(RhAuthorization::canManagePaie($request->user()), 403);

        return new BulletinPaieResource(
            $action->execute($bulletinPaie, $request->validated())
        );
    }

    public function renseignerSalaireBulk(
        RenseignerSalairePercuBulkRequest $request,
        RenseignerSalairePercuBulkAction $action,
    ): JsonResponse {
        abort_unless(RhAuthorization::canManagePaie($request->user()), 403);

        $result = $action->execute($request->validated('items'));

        return response()->json(['data' => $result]);
    }

    public function marquerPaye(
        ReglerBulletinPaieRequest $request,
        BulletinPaie $bulletinPaie,
        ReglerBulletinPaieAction $action,
    ): BulletinPaieResource {
        abort_unless(RhAuthorization::canPayerPaie($request->user()), 403);

        return new BulletinPaieResource($action->execute($bulletinPaie, $request->validated()));
    }

    public function marquerPayeBulk(
        ReglerBulletinPaieBulkRequest $request,
        ReglerBulletinPaieBulkAction $action,
    ): JsonResponse {
        abort_unless(RhAuthorization::canPayerPaie($request->user()), 403);

        $result = $action->execute($request->validated());

        return response()->json(['data' => $result]);
    }
}
