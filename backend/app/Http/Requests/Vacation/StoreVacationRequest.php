<?php

namespace App\Http\Requests\Vacation;

use Illuminate\Foundation\Http\FormRequest;

class StoreVacationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'agent_id' => ['required', 'uuid', 'exists:agents,id'],
            'site_id' => ['required', 'uuid', 'exists:sites,id'],
            'poste_id' => ['nullable', 'uuid', 'exists:postes,id'],
            'date_debut' => ['required', 'date'],
            'date_fin' => ['nullable', 'date', 'after_or_equal:date_debut'],
            'heure_debut' => ['required', 'date_format:H:i'],
            'heure_fin' => ['required', 'date_format:H:i'],
            'statut' => ['nullable', 'string'],
            'annuler_conflits' => ['sometimes', 'boolean'],
        ];
    }
}
