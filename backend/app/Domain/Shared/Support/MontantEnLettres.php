<?php

namespace App\Domain\Shared\Support;

/**
 * Conversion d’un montant entier en lettres (français) pour XOF / FCFA.
 */
final class MontantEnLettres
{
    public static function execute(float|int|string $montant, string $devise = 'francs CFA'): string
    {
        $n = (int) round((float) $montant);
        if ($n === 0) {
            return 'Zéro '.$devise;
        }

        $lettre = self::convert($n);
        $uc = mb_strtoupper(mb_substr($lettre, 0, 1, 'UTF-8'), 'UTF-8')
            .mb_substr($lettre, 1, null, 'UTF-8');

        return $uc.' '.$devise;
    }

    private static function convert(int $n): string
    {
        if ($n < 0) {
            return 'moins '.self::convert(abs($n));
        }

        $units = [
            '', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf',
            'dix', 'onze', 'douze', 'treize', 'quatorze', 'quinze', 'seize',
            'dix-sept', 'dix-huit', 'dix-neuf',
        ];
        $tens = [
            '', '', 'vingt', 'trente', 'quarante', 'cinquante', 'soixante', 'soixante', 'quatre-vingt', 'quatre-vingt',
        ];

        if ($n < 20) {
            return $units[$n];
        }

        if ($n < 100) {
            $d = intdiv($n, 10);
            $u = $n % 10;

            if ($d === 7 || $d === 9) {
                $base = $d === 7 ? 60 : 80;
                $rest = $n - $base;

                return $tens[$d].($rest === 1 && $d === 7 ? ' et ' : '-').self::convert($rest);
            }

            if ($u === 0) {
                return $d === 8 ? 'quatre-vingts' : $tens[$d];
            }

            $sep = ($u === 1 && $d !== 8) ? ' et ' : '-';

            return $tens[$d].$sep.$units[$u];
        }

        if ($n < 1000) {
            $c = intdiv($n, 100);
            $r = $n % 100;
            $prefix = $c === 1 ? 'cent' : $units[$c].' cent';
            if ($r === 0) {
                return $c > 1 ? $units[$c].' cents' : 'cent';
            }

            return $prefix.' '.self::convert($r);
        }

        if ($n < 1_000_000) {
            $m = intdiv($n, 1000);
            $r = $n % 1000;
            $prefix = $m === 1 ? 'mille' : self::convert($m).' mille';

            return $r === 0 ? $prefix : $prefix.' '.self::convert($r);
        }

        if ($n < 1_000_000_000) {
            $m = intdiv($n, 1_000_000);
            $r = $n % 1_000_000;
            $prefix = $m === 1 ? 'un million' : self::convert($m).' millions';

            return $r === 0 ? $prefix : $prefix.' '.self::convert($r);
        }

        $m = intdiv($n, 1_000_000_000);
        $r = $n % 1_000_000_000;
        $prefix = $m === 1 ? 'un milliard' : self::convert($m).' milliards';

        return $r === 0 ? $prefix : $prefix.' '.self::convert($r);
    }
}
