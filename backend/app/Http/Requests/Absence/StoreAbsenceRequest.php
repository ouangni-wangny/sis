<?php

namespace App\Http\Requests\Absence;

use App\Domain\Shared\Enums\StatutAbsence;
use App\Domain\Shared\Enums\TypeAbsence;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAbsenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'agent_id' => ['required', 'uuid', 'exists:agents,id'],
            'type' => ['required', 'string', Rule::enum(TypeAbsence::class)],
            'date_debut' => ['required', 'date'],
            'date_fin' => ['required', 'date', 'after_or_equal:date_debut'],
            'motif' => ['nullable', 'string', 'max:500'],
            'statut' => [
                'nullable',
                'string',
                Rule::enum(StatutAbsence::class),
                Rule::in([
                    StatutAbsence::EnAttente->value,
                    StatutAbsence::Approuvee->value,
                ]),
            ],
        ];
    }
}
