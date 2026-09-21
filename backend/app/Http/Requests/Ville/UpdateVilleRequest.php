<?php

namespace App\Http\Requests\Ville;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVilleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $villeId = $this->route('ville')?->id ?? $this->route('ville');

        return [
            'libelle' => [
                'sometimes',
                'string',
                'max:100',
                Rule::unique('villes', 'libelle')
                    ->ignore($villeId)
                    ->whereNull('deleted_at'),
            ],
        ];
    }
}
