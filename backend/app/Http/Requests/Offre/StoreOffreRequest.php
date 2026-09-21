<?php

namespace App\Http\Requests\Offre;

use Illuminate\Foundation\Http\FormRequest;

class StoreOffreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'libelle' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'prix_mensuel' => ['required', 'numeric', 'min:0'],
            'actif' => ['nullable', 'boolean'],
        ];
    }
}
