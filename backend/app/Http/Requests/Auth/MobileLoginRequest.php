<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class MobileLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'matricule' => ['required', 'string'],
            'pin' => ['required', 'string', 'min:4', 'max:8'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
