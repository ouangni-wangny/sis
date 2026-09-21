<?php

namespace App\Application\Paie;

use App\Models\BulletinPaie;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

final class GenererBulletinPaiePdfAction
{
    public function execute(BulletinPaie $bulletin): BulletinPaie
    {
        $bulletin->load(['agent', 'contrat', 'periodePaie']);

        $html = view('pdf.bulletin-paie', [
            'bulletin' => $bulletin,
            'generatedAt' => now()->timezone('Africa/Abidjan')->format('d/m/Y H:i'),
        ])->render();

        $pdf = Pdf::loadHTML($html)
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => false,
                'defaultFont' => 'DejaVu Sans',
            ]);

        $path = "bulletins/{$bulletin->id}.pdf";
        Storage::disk('private')->put($path, $pdf->output());

        $bulletin->clearMediaCollection('pdf');
        $bulletin->addMedia(Storage::disk('private')->path($path))
            ->toMediaCollection('pdf');

        return $bulletin->fresh('media');
    }
}
