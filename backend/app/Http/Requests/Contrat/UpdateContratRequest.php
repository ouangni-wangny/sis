<?php

namespace App\Http\Requests\Contrat;

use App\Domain\Contrat\ReglesTypeContrat;
use App\Models\Contrat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateContratRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reference' => ['sometimes', 'nullable', 'string', 'max:255'],
            'date_debut' => ['sometimes', 'required', 'date'],
            'date_fin' => ['sometimes', 'nullable', 'date', 'after_or_equal:date_debut'],
            'periode_essai_mois' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:24'],
            'salaire_base' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'indemnite_fonction' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'prime_responsabilite' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'prime_transport' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'prime_entretien_tenue' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'sursalaire' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'nombre_enfants' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:20'],
            'parts_igr' => ['sometimes', 'nullable', 'numeric', 'min:1', 'max:5'],
            'salaire_brut' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'salaire_net' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'salaire' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'statut' => ['sometimes', 'required', 'string', Rule::in(['actif', 'suspendu', 'termine', 'resilie'])],
            'document' => ['sometimes', 'nullable', 'file', 'mimes:pdf', 'max:10240'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var Contrat $contrat */
            $contrat = $this->route('contrat');
            $type = $contrat->type;
            $dateFin = $this->has('date_fin') ? $this->input('date_fin') : $contrat->date_fin?->toDateString();
            $dateDebut = $this->input('date_debut', $contrat->date_debut?->toDateString());
            $periodeEssai = $this->has('periode_essai_mois')
                ? $this->input('periode_essai_mois')
                : $contrat->periode_essai_mois;
            $statut = (string) $this->input('statut', $contrat->statut);

            if ($message = ReglesTypeContrat::validateDateFin($type, (string) $dateDebut, $dateFin)) {
                $validator->errors()->add('date_fin', $message);
            }

            if ($message = ReglesTypeContrat::validatePeriodeEssai($type, $periodeEssai)) {
                $validator->errors()->add('periode_essai_mois', $message);
            }

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ($statut !== 'actif') {
                return;
            }

            $fin = blank($dateFin) ? null : (string) $dateFin;

            if (Contrat::hasActiveOverlap(
                (string) $contrat->agent_id,
                (string) $dateDebut,
                $fin,
                (string) $contrat->id,
            )) {
                $validator->errors()->add(
                    'statut',
                    'Un autre contrat actif chevauche déjà cette période pour cet agent.',
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
