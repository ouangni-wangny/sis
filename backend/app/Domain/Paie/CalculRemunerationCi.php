<?php

namespace App\Domain\Paie;

use App\Domain\Shared\Enums\SituationMatrimoniale;

/**
 * Calculs de rémunération simplifiés — cadre RH Côte d'Ivoire.
 *
 * Règles retenues (Phase 1 SIS) :
 * - Salaire brut = base + indemnités/primes + sursalaire
 * - Parts IGR (quotient familial) : 1 (célibataire/divorcé/veuf) ou 2 (marié)
 *   + 0,5 part par enfant à charge (plafond 5 parts)
 * - CNPS salarié : 6,3 % du brut (plafonné)
 * - IGR mensuel : barème progressif annuel / 12, après abattement forfaitaire 20 %
 * - Net = brut − CNPS − IGR
 *
 * Ces formules sont une base opérationnelle RH ; un expert-comptable pourra
 * affiner barème / plafonds CNPS / exonérations (ex. transport) plus tard.
 */
final class CalculRemunerationCi
{
    public const CNPS_SALARIE_TAUX = 0.063;

    /** Plafond mensuel indicatif CNPS (FCFA) — à ajuster si le plafond officiel change. */
    public const CNPS_PLAFOND_MENSUEL = 1_645_315.0;

    public const PARTS_MAX = 5.0;

    /**
     * @return list<array{max: float|null, rate: float}>
     */
    public static function baremeIgrAnnuel(): array
    {
        return [
            ['max' => 600_000.0, 'rate' => 0.0],
            ['max' => 1_560_000.0, 'rate' => 0.10],
            ['max' => 2_400_000.0, 'rate' => 0.15],
            ['max' => 3_240_000.0, 'rate' => 0.20],
            ['max' => 4_260_000.0, 'rate' => 0.25],
            ['max' => null, 'rate' => 0.30],
        ];
    }

    public static function money(float|int|string|null $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return round((float) $value, 2);
    }

    public static function salaireBrut(
        float|int|string|null $salaireBase,
        float|int|string|null $indemniteFonction = 0,
        float|int|string|null $primeResponsabilite = 0,
        float|int|string|null $primeTransport = 0,
        float|int|string|null $primeEntretienTenue = 0,
        float|int|string|null $sursalaire = 0,
    ): float {
        return round(
            self::money($salaireBase)
            + self::money($indemniteFonction)
            + self::money($primeResponsabilite)
            + self::money($primeTransport)
            + self::money($primeEntretienTenue)
            + self::money($sursalaire),
            2
        );
    }

    public static function partsFiscales(
        SituationMatrimoniale|string|null $situation,
        int $nombreEnfants = 0,
    ): float {
        $enfants = max(0, min(12, $nombreEnfants));
        $sit = $situation instanceof SituationMatrimoniale
            ? $situation
            : SituationMatrimoniale::tryFrom((string) $situation);

        $parts = match ($sit) {
            SituationMatrimoniale::Marie => 2.0,
            SituationMatrimoniale::Veuf => $enfants > 0 ? 1.5 : 1.0,
            default => 1.0,
        };

        $parts += $enfants * 0.5;

        return min(self::PARTS_MAX, round($parts, 1));
    }

    public static function retenueCnps(float $salaireBrut): float
    {
        $assiette = min(max(0.0, $salaireBrut), self::CNPS_PLAFOND_MENSUEL);

        return round($assiette * self::CNPS_SALARIE_TAUX, 2);
    }

    /**
     * IGR mensuel via quotient familial + barème annuel progressif.
     */
    public static function montantIgrMensuel(float $salaireBrut, float $parts): float
    {
        if ($salaireBrut <= 0 || $parts <= 0) {
            return 0.0;
        }

        $revenuAnnuel = $salaireBrut * 12;
        // Abattement forfaitaire 20 % (pratique courante avant application du barème)
        $baseImposable = $revenuAnnuel * 0.80;
        $quotient = $baseImposable / $parts;
        $impotSurQuotient = self::impotProgressif($quotient);
        $igrAnnuel = $impotSurQuotient * $parts;

        return round($igrAnnuel / 12, 2);
    }

    public static function salaireNet(float $salaireBrut, float $retenueCnps, float $montantIgr): float
    {
        return round(max(0.0, $salaireBrut - $retenueCnps - $montantIgr), 2);
    }

    /**
     * @return array{
     *   salaire_brut: float,
     *   parts_igr: float,
     *   montant_igr: float,
     *   retenue_cnps: float,
     *   salaire_net: float
     * }
     */
    public static function calculer(array $input): array
    {
        $brut = self::salaireBrut(
            $input['salaire_base'] ?? 0,
            $input['indemnite_fonction'] ?? 0,
            $input['prime_responsabilite'] ?? 0,
            $input['prime_transport'] ?? 0,
            $input['prime_entretien_tenue'] ?? 0,
            $input['sursalaire'] ?? 0,
        );

        $parts = array_key_exists('parts_igr', $input) && $input['parts_igr'] !== null && $input['parts_igr'] !== ''
            ? (float) $input['parts_igr']
            : self::partsFiscales(
                $input['situation_matrimoniale'] ?? null,
                (int) ($input['nombre_enfants'] ?? 0),
            );

        $parts = min(self::PARTS_MAX, max(1.0, round($parts, 1)));
        $cnps = self::retenueCnps($brut);
        $igr = self::montantIgrMensuel($brut, $parts);
        $net = self::salaireNet($brut, $cnps, $igr);

        return [
            'salaire_brut' => $brut,
            'parts_igr' => $parts,
            'montant_igr' => $igr,
            'retenue_cnps' => $cnps,
            'salaire_net' => $net,
        ];
    }

    private static function impotProgressif(float $montant): float
    {
        $tax = 0.0;
        $previous = 0.0;

        foreach (self::baremeIgrAnnuel() as $tranche) {
            $max = $tranche['max'];
            $rate = $tranche['rate'];
            $ceiling = $max ?? $montant;

            if ($montant <= $previous) {
                break;
            }

            $taxable = min($montant, $ceiling) - $previous;
            if ($taxable > 0) {
                $tax += $taxable * $rate;
            }

            if ($max === null || $montant <= $max) {
                break;
            }

            $previous = $max;
        }

        return $tax;
    }
}
