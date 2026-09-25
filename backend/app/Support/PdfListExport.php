<?php

namespace App\Support;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exports PDF de listes (DomPDF).
 *
 * Plafond haut (5000) : DomPDF reste gourmand ; on monte la mémoire selon le volume.
 */
final class PdfListExport
{
    /** Au-delà, demander un filtre (risque OOM / timeout même à 1.5 Go). */
    public const MAX_ROWS = 5000;

    public static function prepareRuntime(?int $rowCount = null): void
    {
        $memory = '768M';
        if ($rowCount !== null && $rowCount > 800) {
            $memory = '1024M';
        }
        if ($rowCount !== null && $rowCount > 1500) {
            $memory = '1536M';
        }

        if (function_exists('ini_set')) {
            @ini_set('memory_limit', $memory);
        }
        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>|\Illuminate\Database\Query\Builder  $query
     */
    public static function assertQueryCountWithinLimit($query, string $label = 'lignes'): int
    {
        $count = (int) (clone $query)->count();
        if ($count <= self::MAX_ROWS) {
            return $count;
        }

        throw ValidationException::withMessages([
            'export' => sprintf(
                'Trop de %s à exporter (%d). Affinez les filtres (maximum %d).',
                $label,
                $count,
                self::MAX_ROWS,
            ),
        ]);
    }

    public static function download(string $html, string $filename, string $orientation = 'landscape'): Response
    {
        $pdf = Pdf::loadHTML($html)
            ->setPaper('a4', $orientation)
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'defaultFont' => 'DejaVu Sans',
                'isRemoteEnabled' => false,
                'dpi' => 96,
            ]);

        return $pdf->download($filename);
    }
}
