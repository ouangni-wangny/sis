<?php

namespace App\Http\Requests\Paiement;

use App\Domain\Shared\Enums\ModePaiement;
use App\Support\ModePaiementRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaiementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'facture_id' => ['required', 'uuid', 'exists:factures,id'],
            'montant' => ['required', 'numeric', 'min:0.01'],
            'date_paiement' => ['required', 'date'],
            'mode' => ModePaiementRules::required(),
            'compte_tresorerie_id' => ['nullable', 'uuid', 'exists:comptes_tresorerie,id'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
