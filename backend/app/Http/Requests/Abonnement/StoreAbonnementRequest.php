<?php

namespace App\Http\Requests\Abonnement;

use App\Domain\Shared\Enums\PeriodiciteFacturation;
use App\Domain\Shared\Enums\StatutAbonnement;
use App\Models\Site;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (
                ! filled($this->input('site_id'))
                && Site::query()->where('client_id', $this->input('client_id'))->exists()
            ) {
                $validator->errors()->add(
                    'site_id',
                    'Choisissez le site : un abonnement par site, pour des factures séparées.',
                );
            }
        });
    }
}
