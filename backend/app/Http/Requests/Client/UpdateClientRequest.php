<?php

namespace App\Http\Requests\Client;

use App\Domain\Shared\Enums\StatutClient;
use App\Domain\Shared\Enums\TypeClient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClientRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'type' => ['sometimes', Rule::enum(TypeClient::class)],
            'raison_sociale' => ['sometimes', 'string', 'max:255'],
            'nom_responsable' => ['nullable', 'string', 'max:255'],
            'personne_contact' => ['nullable', 'string', 'max:255'],
            'telephone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email'],
            'adresse' => ['nullable', 'string'],
            'statut' => ['sometimes', Rule::enum(StatutClient::class)],
        ];
    }
}
