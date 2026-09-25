<?php

namespace App\Http\Requests\Paie;

use App\Support\ModePaiementRules;
use Illuminate\Foundation\Http\FormRequest;

class ReglerBulletinPaieBulkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bulletin_ids' => ['required', 'array', 'min:1', 'max:2000'],
            'bulletin_ids.*' => ['required', 'uuid'],
            'mode' => ModePaiementRules::required(),
            'compte_tresorerie_id' => ['required', 'uuid', 'exists:comptes_tresorerie,id'],
            'reference' => ['nullable', 'string', 'max:255'],
            'paye_le' => ['nullable', 'date'],
        ];
    }
}
