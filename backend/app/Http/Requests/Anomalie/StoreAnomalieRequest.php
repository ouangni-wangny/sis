<?php

namespace App\Http\Requests\Anomalie;

use App\Domain\Shared\Enums\GraviteAnomalie;
use App\Domain\Shared\Enums\TypeAnomalie;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAnomalieRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'client_uuid' => ['nullable', 'uuid'],
            'signale_par_id' => ['required', 'uuid', 'exists:agents,id'],
            'site_id' => ['required', 'uuid', 'exists:sites,id'],
            'type' => ['required', Rule::enum(TypeAnomalie::class)],
            'gravite' => ['nullable', Rule::enum(GraviteAnomalie::class)],
            'commentaire' => ['nullable', 'string'],
            'signale_at' => ['nullable', 'date'],
            'photos' => ['nullable', 'array'],
            'photos.*' => ['image', 'max:5120'],
        ];
    }
}
