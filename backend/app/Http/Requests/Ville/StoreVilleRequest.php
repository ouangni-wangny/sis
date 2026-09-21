<?php

namespace App\Http\Requests\Ville;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVilleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'libelle' => [
                'required',
                'string',
                'max:100',
                Rule::unique('villes', 'libelle')->whereNull('deleted_at'),
            ],
        ];
    }
}
