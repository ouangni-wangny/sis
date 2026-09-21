<?php

namespace App\Http\Requests\Poste;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePosteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nom' => ['sometimes', 'string', 'max:255'],
            'agents_requis' => ['nullable', 'integer', 'min:1'],
            'heure_debut' => ['nullable', 'date_format:H:i'],
            'heure_fin' => ['nullable', 'date_format:H:i'],
            // Quart nuit optionnel : poste "24h" dès que les deux sont renseignés.
            'heure_debut_nuit' => ['nullable', 'date_format:H:i', 'required_with:heure_fin_nuit'],
            'heure_fin_nuit' => ['nullable', 'date_format:H:i', 'required_with:heure_debut_nuit'],
            'mode_effectif' => ['nullable', 'in:ensemble,alternance'],
        ];
    }
}
