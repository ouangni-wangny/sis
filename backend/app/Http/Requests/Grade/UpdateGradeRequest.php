<?php

namespace App\Http\Requests\Grade;

use App\Domain\Shared\Enums\TypeAgent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGradeRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'libelle' => ['sometimes', 'string', 'max:255'],
            'type_agent' => ['sometimes', Rule::enum(TypeAgent::class)],
            'description' => ['nullable', 'string'],
        ];
    }
}
