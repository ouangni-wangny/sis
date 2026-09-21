<?php

namespace App\Http\Requests\Abonnement;

use App\Domain\Shared\Enums\PeriodiciteFacturation;
use App\Domain\Shared\Enums\StatutAbonnement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAbonnementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_id' => ['sometimes', 'uuid', 'exists:clients,id'],
            'offre_id' => ['sometimes', 'uuid', 'exists:offres,id'],
            'site_id' => ['nullable', 'uuid', 'exists:sites,id'],
            'periodicite' => ['sometimes', Rule::enum(PeriodiciteFacturation::class)],
            'date_debut' => ['sometimes', 'date'],
            'date_fin' => ['nullable', 'date'],
            'statut' => ['nullable', Rule::enum(StatutAbonnement::class)],
        ];
    }
}
