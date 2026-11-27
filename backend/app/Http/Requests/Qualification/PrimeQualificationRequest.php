<?php

namespace App\Http\Requests\Qualification;

use App\Domain\Shared\Enums\UnitePrime;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PrimeQualificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('grades.manage');
    }

    public function rules(): array
    {
        $bareme = $this->route('primeQualification');
        $requis = $this->isMethod('post') ? 'required' : 'sometimes';
        $typeId = $this->input('qualification_id', $bareme?->qualification_id);

        return [
            'qualification_id' => [
                $requis, 'uuid',
                Rule::exists('qualifications', 'id')->whereNull('deleted_at'),
            ],
            'montant' => [$requis, 'numeric', 'min:0', 'max:999999999'],
            'unite' => [
                $requis, Rule::enum(UnitePrime::class),
                // Une seule prime par qualification et par unité (ex. un seul « CP par mois »).
                Rule::unique('primes_qualification', 'unite')
                    ->where('qualification_id', $typeId)
                    ->ignore($bareme?->id)
                    ->whereNull('deleted_at'),
            ],
            'actif' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'unite.unique' => 'Cette qualification a déjà une prime pour cette unité. Modifiez la ligne existante.',
        ];
    }
}
