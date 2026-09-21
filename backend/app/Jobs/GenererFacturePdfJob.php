<?php

namespace App\Jobs;

use App\Models\Facture;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class GenererFacturePdfJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $factureId) {}

    public function handle(): void
    {
        $facture = Facture::query()->with(['client', 'lignes'])->findOrFail($this->factureId);

        $html = view('pdf.facture', [
            'facture' => $facture,
            'generatedAt' => now()->timezone('Africa/Abidjan')->format('d/m/Y H:i'),
        ])->render();

        $pdf = Pdf::loadHTML($html)
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => false,
                'defaultFont' => 'DejaVu Sans',
            ]);
        $path = "factures/{$facture->id}.pdf";
        Storage::disk('media')->put($path, $pdf->output());

        $facture->clearMediaCollection('pdf');
        $facture->addMedia(Storage::disk('media')->path($path))
            ->toMediaCollection('pdf');
    }
}
