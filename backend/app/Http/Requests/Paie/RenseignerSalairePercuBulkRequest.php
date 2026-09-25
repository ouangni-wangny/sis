<?php

namespace App\Http\Requests\Paie;

use Illuminate\Foundation\Http\FormRequest;

class RenseignerSalairePercuBulkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:2000'],
            'items.*.id' => ['required', 'uuid'],
            'items.*.salaire_net' => ['required', 'numeric', 'min:0.01'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Indiquez au moins un bulletin.',
            'items.max' => 'Maximum 2000 bulletins par envoi.',
            'items.*.salaire_net.min' => 'Chaque salaire perçu doit être supérieur à 0.',
        ];
    }
}
