<?php

namespace App\Http\Requests\Vacation;

use Illuminate\Foundation\Http\FormRequest;

class StoreBulkVacationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'vacations' => ['required', 'array', 'min:1', 'max:500'],
            'vacations.*.agent_id' => ['required', 'uuid', 'exists:agents,id'],
            'vacations.*.site_id' => ['required', 'uuid', 'exists:sites,id'],
            'vacations.*.poste_id' => ['nullable', 'uuid', 'exists:postes,id'],
            'vacations.*.date_debut' => ['required', 'date'],
            'vacations.*.date_fin' => ['nullable', 'date', 'after_or_equal:vacations.*.date_debut'],
            'vacations.*.heure_debut' => ['required', 'date_format:H:i'],
            'vacations.*.heure_fin' => ['required', 'date_format:H:i'],
            'annuler_conflits' => ['sometimes', 'boolean'],
            // Remplace les vacations existantes avant création.
            // scope=poste : tout le poste sur la période (Planifier).
            // scope=agents (défaut) : seulement replace.agent_ids.
            'replace' => ['sometimes', 'array'],
            'replace.poste_id' => ['required_with:replace', 'uuid', 'exists:postes,id'],
            'replace.date_debut' => ['required_with:replace', 'date'],
            'replace.date_fin' => ['required_with:replace', 'date', 'after_or_equal:replace.date_debut'],
            'replace.scope' => ['sometimes', 'in:agents,poste'],
            'replace.agent_ids' => ['sometimes', 'array'],
            'replace.agent_ids.*' => ['uuid', 'exists:agents,id'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $replace = $this->input('replace');
            if (! is_array($replace)) {
                return;
            }
            $scope = $replace['scope'] ?? 'agents';
            if ($scope === 'agents' && empty($replace['agent_ids'])) {
                $validator->errors()->add(
                    'replace.agent_ids',
                    'Indiquez les agents à remplacer, ou utilisez scope=poste.',
                );
            }
        });
    }
}
