<?php

namespace App\Support;

use App\Models\Facture;
use Carbon\CarbonInterface;

/**
 * Numéros de facture : SC-ABJ-AAAA-NNNN (tirets, année, séquence).
 * Ex. SC-ABJ-2026-0007 — sûr pour PDF / noms de fichiers (pas de « / » ni « N° »).
 */
final class FactureNumero
{
    public static function next(?CarbonInterface $at = null): string
    {
        $at ??= now();
        $prefix = sprintf(
            '%s-%s-%s-',
            (string) config('sis.facture.numero_prefix', 'SC'),
            (string) config('sis.facture.numero_site', 'ABJ'),
            $at->format('Y'),
        );

        $next = Facture::query()
            ->withTrashed()
            ->where('numero', 'like', $prefix.'%')
            ->count() + 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
