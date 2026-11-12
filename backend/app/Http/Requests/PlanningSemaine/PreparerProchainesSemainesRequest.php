<?php

namespace App\Http\Requests\PlanningSemaine;

use Illuminate\Foundation\Http\FormRequest;

class PreparerProchainesSemainesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'poste_id' => ['required', 'uuid', 'exists:postes,id'],
            'nombre' => ['sometimes', 'integer', 'min:1', 'max:12'],
        ];
    }
}
