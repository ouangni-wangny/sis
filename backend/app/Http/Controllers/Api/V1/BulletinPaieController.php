<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Paie\GenererBulletinPaiePdfAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\BulletinPaieResource;
use App\Models\BulletinPaie;
use App\Support\RhAuthorization;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BulletinPaieController extends Controller
{
    public function show(Request $request, BulletinPaie $bulletinPaie): BulletinPaieResource
    {
        abort_unless(RhAuthorization::canViewPaie($request->user()), 403);

        return new BulletinPaieResource($bulletinPaie->load(['agent', 'contrat', 'periodePaie', 'media']));
    }

    public function genererPdf(
        Request $request,
        BulletinPaie $bulletinPaie,
        GenererBulletinPaiePdfAction $action,
    ): BulletinPaieResource {
        abort_unless(RhAuthorization::canManagePaie($request->user()), 403);

        return new BulletinPaieResource($action->execute($bulletinPaie)->load('media'));
    }

    public function downloadPdf(Request $request, BulletinPaie $bulletinPaie): BinaryFileResponse
    {
        abort_unless(RhAuthorization::canViewPaie($request->user()), 403);

        $media = $bulletinPaie->getFirstMedia('pdf');
        abort_unless($media, 404);

        return response()->download(
            $media->getPath(),
            "bulletin-{$bulletinPaie->id}.pdf",
        );
    }

    public function marquerPaye(Request $request, BulletinPaie $bulletinPaie): BulletinPaieResource
    {
        abort_unless(RhAuthorization::canManagePaie($request->user()), 403);

        $bulletinPaie->update([
            'statut' => 'paye',
            'paye_le' => now(),
        ]);

        return new BulletinPaieResource($bulletinPaie);
    }
}
