<?php

namespace App\Http\Requests\Rapport;

use Illuminate\Foundation\Http\FormRequest;

class StoreRapportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'format' => ['required', 'in:pdf,xlsx'],
            'filtres' => ['required', 'array'],
            'filtres.date_debut' => ['required', 'date'],
            'filtres.date_fin' => ['required', 'date', 'after_or_equal:filtres.date_debut'],
        ];
    }
}
