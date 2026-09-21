<?php

namespace App\Http\Requests\Contrat;

use App\Domain\Contrat\ReglesTypeContrat;
use App\Domain\Shared\Enums\TypeContrat;
use App\Models\Contrat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreContratRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'agent_id' => ['required', 'uuid', 'exists:agents,id'],
            'contrat_parent_id' => ['nullable', 'uuid', 'exists:contrats,id'],
            'type' => ['required', Rule::enum(TypeContrat::class)],
            'reference' => ['nullable', 'string', 'max:255'],
            'date_debut' => ['required', 'date'],
            'date_fin' => ['nullable', 'date', 'after_or_equal:date_debut'],
            'periode_essai_mois' => ['nullable', 'integer', 'min:0', 'max:24'],
            'salaire_base' => ['nullable', 'numeric', 'min:0'],
            'indemnite_fonction' => ['nullable', 'numeric', 'min:0'],
            'prime_responsabilite' => ['nullable', 'numeric', 'min:0'],
            'prime_transport' => ['nullable', 'numeric', 'min:0'],
            'prime_entretien_tenue' => ['nullable', 'numeric', 'min:0'],
            'sursalaire' => ['nullable', 'numeric', 'min:0'],
            'nombre_enfants' => ['nullable', 'integer', 'min:0', 'max:20'],
            'parts_igr' => ['nullable', 'numeric', 'min:1', 'max:5'],
            'salaire_brut' => ['nullable', 'numeric', 'min:0'],
            'salaire_net' => ['nullable', 'numeric', 'min:0'],
            'salaire' => ['nullable', 'numeric', 'min:0'],
            'statut' => ['nullable', 'string', Rule::in(['actif', 'suspendu', 'termine', 'resilie'])],
            'document' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $type = (string) $this->input('type');
            $dateDebut = (string) $this->input('date_debut');
            $dateFin = $this->input('date_fin');
            $periodeEssai = $this->input('periode_essai_mois');

            if ($message = ReglesTypeContrat::validateDateFin($type, $dateDebut, $dateFin)) {
                $validator->errors()->add('date_fin', $message);
            }

            if ($message = ReglesTypeContrat::validatePeriodeEssai($type, $periodeEssai)) {
                $validator->errors()->add('periode_essai_mois', $message);
            }

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $statut = (string) ($this->input('statut') ?? 'actif');
            if ($statut !== 'actif') {
                return;
            }

            $agentId = (string) $this->input('agent_id');
            $debut = (string) $this->input('date_debut');
            $fin = blank($dateFin) ? null : (string) $dateFin;

            if (Contrat::hasActiveOverlap($agentId, $debut, $fin)) {
                $validator->errors()->add(
                    'agent_id',
                    'Cet agent a déjà un contrat actif sur cette période. Clôturez ou résiliez l’existant avant d’en créer un nouveau (ex. CDD puis CDI).',
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'date_fin.after_or_equal' => 'La date de fin doit être postérieure ou égale à la date de début.',
        ];
    }
}
