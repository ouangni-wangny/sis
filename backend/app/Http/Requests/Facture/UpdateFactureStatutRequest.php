<?php

namespace App\Http\Requests\Facture;

use App\Domain\Shared\Enums\StatutFacture;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFactureStatutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'statut' => ['required', Rule::enum(StatutFacture::class)],
        ];
    }
}
