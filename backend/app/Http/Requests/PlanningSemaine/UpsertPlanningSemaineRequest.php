<?php

namespace App\Http\Requests\PlanningSemaine;

use Illuminate\Foundation\Http\FormRequest;

class UpsertPlanningSemaineRequest extends FormRequest
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
            'creneaux' => ['present', 'array'],
            'creneaux.*.agent_id' => ['required', 'uuid', 'exists:agents,id'],
            'creneaux.*.jour' => ['required', 'integer', 'min:1', 'max:7'],
            'creneaux.*.heure_debut' => ['required', 'date_format:H:i'],
            'creneaux.*.heure_fin' => ['required', 'date_format:H:i'],
            'creneaux.*.quart' => ['sometimes', 'in:jour,nuit'],
        ];
    }
}
