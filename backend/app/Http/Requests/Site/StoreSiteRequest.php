<?php

namespace App\Http\Requests\Site;

use Illuminate\Foundation\Http\FormRequest;

class StoreSiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_id' => ['required', 'uuid', 'exists:clients,id'],
            'zone_id' => ['required', 'uuid', 'exists:zones,id'],
            'nom' => ['required', 'string', 'max:255'],
            'adresse' => ['nullable', 'string'],
            'responsable' => ['nullable', 'string', 'max:255'],
            'tarif_mensuel' => ['nullable', 'numeric', 'min:0'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'rayon_metres' => ['nullable', 'integer', 'min:50'],
            'interne' => ['sometimes', 'boolean'],
            'postes' => ['nullable', 'array'],
            'postes.*.nom' => ['required_with:postes', 'string'],
            'postes.*.agents_requis' => ['nullable', 'integer', 'min:1'],
            'postes.*.heure_debut' => ['nullable', 'date_format:H:i'],
            'postes.*.heure_fin' => ['nullable', 'date_format:H:i'],
            'postes.*.heure_debut_nuit' => ['nullable', 'date_format:H:i'],
            'postes.*.heure_fin_nuit' => ['nullable', 'date_format:H:i'],
            'postes.*.mode_effectif' => ['nullable', 'in:ensemble,alternance'],
        ];
    }
}
