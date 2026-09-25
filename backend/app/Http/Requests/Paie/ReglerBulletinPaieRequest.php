<?php

namespace App\Http\Requests\Paie;

use App\Support\ModePaiementRules;
use Illuminate\Foundation\Http\FormRequest;

class ReglerBulletinPaieRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mode' => ModePaiementRules::required(),
            'compte_tresorerie_id' => ['required', 'uuid', 'exists:comptes_tresorerie,id'],
            'reference' => ['nullable', 'string', 'max:255'],
            'paye_le' => ['nullable', 'date'],
        ];
    }
}
