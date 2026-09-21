<?php

namespace App\Http\Requests\Abonnement;

use App\Domain\Shared\Enums\PeriodiciteFacturation;
use App\Domain\Shared\Enums\StatutAbonnement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAbonnementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_id' => ['required', 'uuid', 'exists:clients,id'],
            'offre_id' => ['required', 'uuid', 'exists:offres,id'],
            'site_id' => ['nullable', 'uuid', 'exists:sites,id'],
            'periodicite' => ['required', Rule::enum(PeriodiciteFacturation::class)],
            'date_debut' => ['required', 'date'],
            'date_fin' => ['nullable', 'date', 'after_or_equal:date_debut'],
            'statut' => ['nullable', Rule::enum(StatutAbonnement::class)],
        ];
    }
}
