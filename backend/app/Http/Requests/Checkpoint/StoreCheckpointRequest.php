<?php

namespace App\Http\Requests\Checkpoint;

use Illuminate\Foundation\Http\FormRequest;

class StoreCheckpointRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:255'],
            'code_qr' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'ordre' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
