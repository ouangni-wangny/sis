<?php

namespace App\Http\Requests\Site;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_id' => ['sometimes', 'uuid', 'exists:clients,id'],
            'zone_id' => ['sometimes', 'uuid', 'exists:zones,id'],
            'nom' => ['sometimes', 'string', 'max:255'],
            'adresse' => ['nullable', 'string'],
            'responsable' => ['nullable', 'string', 'max:255'],
            'tarif_mensuel' => ['nullable', 'numeric', 'min:0'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'rayon_metres' => ['nullable', 'integer', 'min:50'],
            'interne' => ['sometimes', 'boolean'],
        ];
    }
}
