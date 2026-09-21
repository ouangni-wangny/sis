<?php

namespace App\Http\Requests\Ronde;

use Illuminate\Foundation\Http\FormRequest;

class ScannerCheckpointRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'checkpoint_id' => ['required', 'uuid', 'exists:checkpoints,id'],
            'latitude' => ['required', 'numeric'],
            'longitude' => ['required', 'numeric'],
            'code_qr' => ['required', 'string'],
        ];
    }
}
