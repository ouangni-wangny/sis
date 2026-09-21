<?php

namespace App\Domain\Contrat;

use App\Domain\Shared\Enums\TypeContrat;
use Carbon\Carbon;

final class ReglesTypeContrat
{
    /**
     * @return array{
     *   needsDateFin: bool,
     *   dureeMaxMois: int|null,
     *   essaiVisible: bool,
     *   essaiDefautMois: int|null,
     *   essaiMaxMois: int|null,
     * }
     */
    public static function for(TypeContrat|string $type): array
    {
        $value = $type instanceof TypeContrat ? $type->value : $type;

        return match ($value) {
            TypeContrat::Cdi->value => [
                'needsDateFin' => false,
                'dureeMaxMois' => null,
                'essaiVisible' => true,
                'essaiDefautMois' => 3,
                'essaiMaxMois' => 6,
            ],
            TypeContrat::Cdd->value => [
                'needsDateFin' => true,
                'dureeMaxMois' => 24,
                'essaiVisible' => true,
                'essaiDefautMois' => 1,
                'essaiMaxMois' => 2,
            ],
            TypeContrat::Stage->value => [
                'needsDateFin' => true,
                'dureeMaxMois' => 12,
                'essaiVisible' => false,
                'essaiDefautMois' => null,
                'essaiMaxMois' => null,
            ],
            TypeContrat::Prestation->value => [
                'needsDateFin' => true,
                'dureeMaxMois' => 36,
                'essaiVisible' => false,
                'essaiDefautMois' => null,
                'essaiMaxMois' => null,
            ],
            default => throw new \InvalidArgumentException("Type de contrat inconnu : {$value}"),
        };
    }

    public static function needsDateFin(TypeContrat|string $type): bool
    {
        return self::for($type)['needsDateFin'];
    }

    public static function validateDateFin(
        TypeContrat|string $type,
        string $dateDebut,
        ?string $dateFin,
    ): ?string {
        $rules = self::for($type);

        if ($rules['needsDateFin'] && blank($dateFin)) {
            $label = strtoupper($type instanceof TypeContrat ? $type->value : $type);

            return "Une date de fin est obligatoire pour un contrat {$label}.";
        }

        if (blank($dateFin)) {
            return null;
        }

        $debut = Carbon::parse($dateDebut)->startOfDay();
        $fin = Carbon::parse($dateFin)->startOfDay();

        if ($fin->lt($debut)) {
            return 'La date de fin doit être postérieure ou égale à la date de début.';
        }

        $maxMois = $rules['dureeMaxMois'];
        if ($maxMois !== null) {
            $limite = $debut->copy()->addMonths($maxMois);
            if ($fin->gt($limite)) {
                $label = strtoupper($type instanceof TypeContrat ? $type->value : $type);

                return "La durée maximale d’un contrat {$label} est de {$maxMois} mois.";
            }
        }

        return null;
    }

    public static function validatePeriodeEssai(
        TypeContrat|string $type,
        mixed $periodeEssaiMois,
    ): ?string {
        $rules = self::for($type);

        if (! $rules['essaiVisible']) {
            if ($periodeEssaiMois !== null && $periodeEssaiMois !== '' && (int) $periodeEssaiMois > 0) {
                return 'La période d’essai ne s’applique pas à ce type de contrat.';
            }

            return null;
        }

        if ($periodeEssaiMois === null || $periodeEssaiMois === '') {
            return null;
        }

        $mois = (int) $periodeEssaiMois;
        if ($mois < 0) {
            return 'La période d’essai ne peut pas être négative.';
        }

        $max = $rules['essaiMaxMois'];
        if ($max !== null && $mois > $max) {
            $label = strtoupper($type instanceof TypeContrat ? $type->value : $type);

            return "La période d’essai maximale pour un {$label} est de {$max} mois.";
        }

        return null;
    }
}
