<?php

namespace App\Http\Requests\Offre;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOffreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'libelle' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'prix_mensuel' => ['sometimes', 'numeric', 'min:0'],
            'actif' => ['nullable', 'boolean'],
        ];
    }
}
