<?php

namespace App\Http\Requests\Agent;

use Illuminate\Foundation\Http\FormRequest;

class SyncPerimetreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Un rondier ne gère qu'une seule zone à la fois. Un tableau
            // vide reste autorisé : c'est ainsi qu'on libère le périmètre
            // (ex: l'agent n'est plus rondier).
            'items' => ['present', 'array', 'max:1'],
            'items.*.zone_id' => ['required', 'uuid', 'exists:zones,id'],
        ];
    }
}
