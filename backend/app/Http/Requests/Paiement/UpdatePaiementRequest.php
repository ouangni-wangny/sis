<?php

namespace App\Http\Requests\Paiement;

use App\Support\ModePaiementRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePaiementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'facture_id' => ['sometimes', 'uuid', 'exists:factures,id'],
            'montant' => ['sometimes', 'numeric', 'min:0.01'],
            'date_paiement' => ['sometimes', 'date'],
            'mode' => ModePaiementRules::sometimes(),
            'compte_tresorerie_id' => ['nullable', 'uuid', 'exists:comptes_tresorerie,id'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
