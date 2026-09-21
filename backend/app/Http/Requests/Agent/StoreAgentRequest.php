<?php

namespace App\Http\Requests\Agent;

use App\Domain\Shared\Enums\Civilite;
use App\Domain\Shared\Enums\JourSemaine;
use App\Domain\Shared\Enums\SituationMatrimoniale;
use App\Domain\Shared\Enums\StatutAgent;
use App\Models\Poste;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAgentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'grade_id' => ['required', 'uuid', 'exists:grades,id'],
            'nom' => ['required', 'string', 'max:255'],
            'prenom' => ['required', 'string', 'max:255'],
            'civilite' => ['nullable', 'string', Rule::enum(Civilite::class)],
            'date_naissance' => ['nullable', 'date', 'before:today'],
            'lieu_naissance' => ['nullable', 'string', 'max:255'],
            'situation_matrimoniale' => ['nullable', 'string', Rule::enum(SituationMatrimoniale::class)],
            'nombre_enfants' => ['nullable', 'integer', 'min:0', 'max:20'],
            'nationalite' => ['nullable', 'string', 'max:100'],
            'telephone' => ['nullable', 'string', 'max:50'],
            'numero_cni' => ['nullable', 'string', 'max:50'],
            'ville_id' => [
                'nullable',
                'uuid',
                Rule::exists('villes', 'id')->whereNull('deleted_at'),
            ],
            'domicile' => ['nullable', 'string', 'max:1000'],
            'cnps' => ['nullable', 'string', 'max:50'],
            'date_embauche' => ['nullable', 'date'],
            'date_expiration_permis' => ['nullable', 'date'],
            'statut' => ['nullable', 'string', Rule::enum(StatutAgent::class)],
            'pool_siege' => ['nullable', 'boolean'],
            'poste_siege_id' => [
                'nullable',
                'uuid',
                Rule::exists('postes', 'id')->whereNull('deleted_at'),
            ],
            'jour_repos' => ['nullable', 'string', Rule::enum(JourSemaine::class)],
            'pin' => ['nullable', 'string', 'min:4', 'max:8'],
            'email' => ['nullable', 'email'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->boolean('pool_siege')) {
                return;
            }

            if (! $this->filled('poste_siege_id')) {
                $validator->errors()->add(
                    'poste_siege_id',
                    'Indiquez le poste siège où pointer la présence de cet agent du pool remplaçant.',
                );

                return;
            }

            $poste = Poste::query()->with('site')->find($this->input('poste_siege_id'));
            if ($poste && ! $poste->site?->interne) {
                $validator->errors()->add(
                    'poste_siege_id',
                    'Le poste siège doit appartenir à un site marqué « interne ».',
                );
            }
        });
    }
}
