<?php

namespace App\Jobs;

use App\Application\Reporting\BuildRapportDatasetAction;
use App\Models\RapportExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Facades\Excel;

class GenererRapportJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $rapportId) {}

    public function handle(BuildRapportDatasetAction $builder): void
    {
        $rapport = RapportExport::query()->findOrFail($this->rapportId);
        $rapport->update(['statut' => 'processing']);

        try {
            $dataset = $builder->execute($rapport);

            if ($rapport->format === 'xlsx') {
                $this->exportExcel($rapport, $dataset);
            } else {
                $this->exportPdf($rapport, $dataset);
            }

            $rapport->update(['statut' => 'done']);
        } catch (\Throwable $e) {
            $rapport->update(['statut' => 'failed', 'erreur' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $dataset
     */
    private function exportPdf(RapportExport $rapport, array $dataset): void
    {
        $html = view('pdf.rapport', [
            'rapport' => $rapport,
            'dataset' => $dataset,
            'generatedAt' => now()->timezone('Africa/Abidjan')->format('d/m/Y H:i'),
        ])->render();

        $pdf = Pdf::loadHTML($html)
            ->setPaper('a4', 'landscape')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'defaultFont' => 'DejaVu Sans',
            ]);

        $path = "rapports/{$rapport->id}.pdf";
        Storage::disk('media')->put($path, $pdf->output());

        $rapport->clearMediaCollection('export');
        $rapport->addMedia(Storage::disk('media')->path($path))
            ->toMediaCollection('export');
    }

    /**
     * @param  array<string, mixed>  $dataset
     */
    private function exportExcel(RapportExport $rapport, array $dataset): void
    {
        $rows = [
            [$dataset['title'] ?? 'Rapport S.I.S'],
            [$dataset['subtitle'] ?? ''],
            [$dataset['period_label'] ?? 'Période : non filtrée'],
            ['Généré le', now()->timezone('Africa/Abidjan')->format('d/m/Y H:i')],
            [],
            $dataset['columns'] ?? [],
            ...($dataset['rows'] ?? []),
        ];

        if (! empty($dataset['summary'])) {
            $rows[] = [];
            $rows[] = ['Indicateurs'];
            foreach ($dataset['summary'] as $item) {
                $rows[] = [$item['label'] ?? '', $item['value'] ?? ''];
            }
        }

        $export = new class($rows) implements FromArray
        {
            public function __construct(private array $rows) {}

            public function array(): array
            {
                return $this->rows;
            }
        };

        $filename = "rapports/{$rapport->id}.xlsx";
        Excel::store($export, $filename, 'media');

        $rapport->clearMediaCollection('export');
        $rapport->addMedia(Storage::disk('media')->path($filename))
            ->toMediaCollection('export');
    }
}
