<?php

namespace App\Support;

use App\Models\ModePaiementParam;
use Illuminate\Validation\Rule;

final class ModePaiementRules
{
    /** @return list<\Illuminate\Contracts\Validation\ValidationRule|string> */
    public static function required(): array
    {
        return [
            'required',
            'string',
            'max:50',
            Rule::exists('modes_paiement', 'code')->where(fn ($q) => $q->where('actif', true)),
        ];
    }

    /** @return list<\Illuminate\Contracts\Validation\ValidationRule|string> */
    public static function sometimes(): array
    {
        return [
            'sometimes',
            'string',
            'max:50',
            Rule::exists('modes_paiement', 'code'),
        ];
    }

    public static function label(string $code): string
    {
        $row = ModePaiementParam::query()->where('code', $code)->first();

        return $row?->libelle ?? $code;
    }
}
