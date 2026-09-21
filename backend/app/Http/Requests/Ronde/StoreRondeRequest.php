<?php

namespace App\Http\Requests\Ronde;

use Illuminate\Foundation\Http\FormRequest;

class StoreRondeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'agent_id' => ['required', 'uuid', 'exists:agents,id'],
            'site_id' => ['required', 'uuid', 'exists:sites,id'],
            'vacation_id' => ['nullable', 'uuid', 'exists:vacations,id'],
        ];
    }
}
