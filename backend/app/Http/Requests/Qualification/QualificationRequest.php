<?php

namespace App\Http\Requests\Qualification;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QualificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('grades.manage');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('abreviation')) {
            $this->merge(['abreviation' => mb_strtoupper(trim((string) $this->input('abreviation')))]);
        }
    }

    public function rules(): array
    {
        $id = $this->route('qualification')?->id;
        $requis = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'libelle' => [
                $requis, 'string', 'max:100',
                Rule::unique('qualifications', 'libelle')->ignore($id)->whereNull('deleted_at'),
            ],
            'abreviation' => [
                $requis, 'string', 'max:10', 'regex:/^[A-Z0-9]+$/',
                Rule::unique('qualifications', 'abreviation')->ignore($id)->whereNull('deleted_at'),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'actif' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'libelle.unique' => 'Cette qualification existe déjà.',
            'abreviation.unique' => 'Cette abréviation est déjà utilisée.',
            'abreviation.regex' => 'L’abréviation ne contient que des lettres et des chiffres (ex. CP).',
        ];
    }
}
