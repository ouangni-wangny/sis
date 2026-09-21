<?php

namespace App\Http\Requests\Anomalie;

use App\Domain\Shared\Enums\StatutAnomalie;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAnomalieStatutRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'statut' => ['required', Rule::enum(StatutAnomalie::class)],
            'assigne_a_id' => ['nullable', 'uuid', 'exists:users,id'],
        ];
    }
}
