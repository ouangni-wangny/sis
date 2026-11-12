<?php

namespace App\Http\Requests\PlanningSemaine;

use Illuminate\Foundation\Http\FormRequest;

class DupliquerPlanningSemaineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'poste_id' => ['required', 'uuid', 'exists:postes,id'],
            'annee_iso' => ['required', 'integer', 'min:2020', 'max:2100'],
            'semaine_iso' => ['required', 'integer', 'min:1', 'max:53'],
            'source_annee_iso' => ['sometimes', 'integer', 'min:2020', 'max:2100'],
            'source_semaine_iso' => ['sometimes', 'integer', 'min:1', 'max:53'],
        ];
    }
}
