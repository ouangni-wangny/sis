<?php

namespace App\Http\Requests\Paie;

use Illuminate\Foundation\Http\FormRequest;

class RenseignerSalairePercuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'salaire_net' => ['required', 'numeric', 'min:0.01'],
        ];
    }

    public function messages(): array
    {
        return [
            'salaire_net.required' => 'Indiquez le salaire perçu ce mois.',
            'salaire_net.min' => 'Le salaire perçu doit être supérieur à 0.',
        ];
    }
}
