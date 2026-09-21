<?php

namespace App\Http\Requests\Zone;

use Illuminate\Foundation\Http\FormRequest;

class SyncZoneControleursRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'agent_ids' => ['present', 'array', 'max:2'],
            'agent_ids.*' => ['uuid', 'distinct', 'exists:agents,id'],
        ];
    }
}
