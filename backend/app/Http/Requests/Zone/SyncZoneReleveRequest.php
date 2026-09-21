<?php

namespace App\Http\Requests\Zone;

use Illuminate\Foundation\Http\FormRequest;

class SyncZoneReleveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'releve_depuis' => ['required', 'date'],
            'releve_jusque' => ['required', 'date', 'after_or_equal:releve_depuis'],
            'ordre_agent_ids' => ['sometimes', 'array', 'size:2'],
            'ordre_agent_ids.*' => ['uuid', 'distinct', 'exists:agents,id'],
        ];
    }
}
