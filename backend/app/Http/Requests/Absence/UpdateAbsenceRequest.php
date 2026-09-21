<?php

namespace App\Http\Requests\Absence;

use App\Domain\Shared\Enums\StatutAbsence;
use App\Domain\Shared\Enums\TypeAbsence;
use App\Models\Absence;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateAbsenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'string', Rule::enum(TypeAbsence::class)],
            'date_debut' => ['sometimes', 'date'],
            'date_fin' => ['sometimes', 'date'],
            'motif' => ['nullable', 'string', 'max:500'],
            'statut' => ['sometimes', 'string', Rule::enum(StatutAbsence::class)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var Absence $absence */
            $absence = $this->route('absence');
            $debut = $this->input('date_debut', $absence->date_debut->format('Y-m-d'));
            $fin = $this->input('date_fin', $absence->date_fin->format('Y-m-d'));

            if ($fin < $debut) {
                $validator->errors()->add(
                    'date_fin',
                    'La date de fin doit être postérieure ou égale à la date de début.',
                );
            }
        });
    }
}
