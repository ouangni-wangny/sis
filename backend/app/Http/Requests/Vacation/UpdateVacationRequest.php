<?php

namespace App\Http\Requests\Vacation;

use App\Domain\Shared\Enums\StatutVacation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVacationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'agent_id' => ['sometimes', 'uuid', 'exists:agents,id'],
            'site_id' => ['sometimes', 'uuid', 'exists:sites,id'],
            'poste_id' => ['nullable', 'uuid', 'exists:postes,id'],
            'date_debut' => ['sometimes', 'date'],
            'date_fin' => ['nullable', 'date', 'after_or_equal:date_debut'],
            'heure_debut' => ['sometimes', 'date_format:H:i'],
            'heure_fin' => ['sometimes', 'date_format:H:i'],
            'statut' => ['sometimes', Rule::enum(StatutVacation::class)],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $debut = $this->input('date_debut');
            $fin = $this->input('date_fin');
            // Si date_debut n’est pas dans la requête, after_or_equal ne s’applique
            // pas correctement — on compare à la vacation existante si besoin.
            if ($fin && ! $debut && $this->route('vacation')) {
                $vacation = $this->route('vacation');
                $existingDebut = $vacation->date_debut?->format('Y-m-d');
                if ($existingDebut && $fin < $existingDebut) {
                    $validator->errors()->add(
                        'date_fin',
                        'La date de fin doit être postérieure ou égale à la date de début.',
                    );
                }
            }
        });
    }
}
