<?php

namespace App\Http\Requests\Agent;

use App\Domain\Shared\Enums\Civilite;
use App\Domain\Shared\Enums\JourSemaine;
use App\Domain\Shared\Enums\SituationMatrimoniale;
use App\Domain\Shared\Enums\StatutAgent;
use App\Models\Agent;
use App\Models\Poste;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateAgentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'grade_id' => ['sometimes', 'uuid', 'exists:grades,id'],
            'nom' => ['sometimes', 'string', 'max:255'],
            'prenom' => ['sometimes', 'string', 'max:255'],
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
            'statut' => ['sometimes', 'string', Rule::enum(StatutAgent::class)],
            'pool_siege' => ['nullable', 'boolean'],
            'poste_siege_id' => [
                'nullable',
                'uuid',
                Rule::exists('postes', 'id')->whereNull('deleted_at'),
            ],
            'jour_repos' => ['nullable', 'string', Rule::enum(JourSemaine::class)],
            'email' => ['nullable', 'email', 'max:255'],
            'pin' => ['nullable', 'string', 'min:4', 'max:8'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Agent|null $agent */
            $agent = $this->route('agent');

            $poolSiege = $this->has('pool_siege')
                ? $this->boolean('pool_siege')
                : (bool) $agent?->pool_siege;

            if (! $poolSiege) {
                return;
            }

            $posteId = $this->has('poste_siege_id')
                ? $this->input('poste_siege_id')
                : $agent?->poste_siege_id;

            if (! filled($posteId)) {
                $validator->errors()->add(
                    'poste_siege_id',
                    'Indiquez le poste siège où pointer la présence de cet agent du pool remplaçant.',
                );

                return;
            }

            $poste = Poste::query()->with('site')->find($posteId);
            if ($poste && ! $poste->site?->interne) {
                $validator->errors()->add(
                    'poste_siege_id',
                    'Le poste siège doit appartenir à un site marqué « interne ».',
                );
            }
        });
    }
}
