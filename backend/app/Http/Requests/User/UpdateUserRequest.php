<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $allowedRoles = ['super-admin', 'operation', 'rh', 'commercial'];
        if ($this->user()?->hasRole('developpeur')) {
            $allowedRoles[] = 'developpeur';
        }

        return [
            'nom' => ['sometimes', 'string', 'max:255'],
            'prenom' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email'],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => [
                'nullable',
                'string',
                Rule::in($allowedRoles),
            ],
            'statut' => ['sometimes', 'string', Rule::in(['actif', 'inactif', 'bloque'])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (
                $this->input('role') === 'developpeur'
                && ! $this->user()?->hasRole('developpeur')
            ) {
                $validator->errors()->add(
                    'role',
                    'Seul un développeur peut assigner le rôle développeur.',
                );
            }
        });
    }
}
