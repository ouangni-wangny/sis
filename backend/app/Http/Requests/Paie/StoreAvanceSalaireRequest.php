<?php

namespace App\Http\Requests\Paie;

use App\Support\ModePaiementRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreAvanceSalaireRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'agent_id' => ['required', 'uuid', 'exists:agents,id,deleted_at,NULL'],
            'montant' => ['required', 'numeric', 'gt:0'],
            'date_avance' => ['required', 'date', 'before_or_equal:today'],
            'mois' => ['required', 'integer', 'between:1,12'],
            'annee' => ['required', 'integer', 'between:2000,2100'],
            'mode' => ModePaiementRules::required(),
            'compte_tresorerie_id' => ['required', 'uuid', 'exists:comptes_tresorerie,id'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'date_avance.before_or_equal' => 'La date de l’avance ne peut pas être dans le futur.',
            'montant.gt' => 'Le montant doit être supérieur à 0.',
        ];
    }
}
