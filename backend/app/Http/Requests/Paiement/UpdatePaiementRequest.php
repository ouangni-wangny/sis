<?php

namespace App\Http\Requests\Paiement;

use App\Domain\Shared\Enums\ModePaiement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'mode' => ['sometimes', Rule::enum(ModePaiement::class)],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
