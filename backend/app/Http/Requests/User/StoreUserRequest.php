<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $allowedRoles = ['super-admin', 'operation', 'rh', 'commercial', 'comptable'];
        if ($this->user()?->hasRole('developpeur')) {
            $allowedRoles[] = 'developpeur';
        }

        return [
            'nom' => ['required', 'string', 'max:255'],
            'prenom' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => [
                'required',
                'string',
                Rule::in($allowedRoles),
            ],
            'statut' => ['nullable', 'string', Rule::in(['actif', 'inactif', 'bloque'])],
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
