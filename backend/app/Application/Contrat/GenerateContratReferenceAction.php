<?php

namespace App\Application\Contrat;

use App\Domain\Shared\Enums\TypeContrat;
use App\Models\Contrat;

/**
 * Nomenclature référence contrat RH :
 * CTR-{TYPE}-{YYYY}-{####}
 *
 * Exemples : CTR-CDI-2026-0001 · CTR-CDD-2026-0002 · CTR-STG-2026-0001
 */
final class GenerateContratReferenceAction
{
    public function execute(TypeContrat|string $type, ?string $annee = null): string
    {
        $typeCode = $this->typeCode($type);
        $year = $annee ?? now()->timezone('Africa/Abidjan')->format('Y');
        $prefix = sprintf('CTR-%s-%s-', $typeCode, $year);
        $legacyPrefix = sprintf('CTR/%s/%s/', $typeCode, $year);

        $existing = Contrat::withTrashed()
            ->where(function ($query) use ($prefix, $legacyPrefix) {
                $query->where('reference', 'like', $prefix.'%')
                    ->orWhere('reference', 'like', $legacyPrefix.'%');
            })
            ->pluck('reference');

        $max = 0;
        foreach ($existing as $reference) {
            $ref = (string) $reference;
            if (preg_match('/^'.preg_quote($prefix, '/').'(\d{4})$/', $ref, $matches) === 1) {
                $max = max($max, (int) $matches[1]);
            } elseif (preg_match('/^'.preg_quote($legacyPrefix, '/').'(\d{4})$/', $ref, $matches) === 1) {
                $max = max($max, (int) $matches[1]);
            }
        }

        return $prefix.sprintf('%04d', $max + 1);
    }

    private function typeCode(TypeContrat|string $type): string
    {
        $value = $type instanceof TypeContrat ? $type->value : strtolower((string) $type);

        return match ($value) {
            TypeContrat::Cdi->value => 'CDI',
            TypeContrat::Cdd->value => 'CDD',
            TypeContrat::Stage->value => 'STG',
            TypeContrat::Prestation->value => 'PRE',
            default => strtoupper(substr($value, 0, 3)),
        };
    }
}
