<?php

namespace App\Http\Requests\Contrat;

use App\Domain\Contrat\ReglesTypeContrat;
use App\Domain\Shared\Enums\TypeContrat;
use App\Models\Contrat;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RenouvelerContratRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
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
            'parts_igr' => ['nullable', 'numeric', 'min:1', 'max:5'],
            'document' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var Contrat $source */
            $source = $this->route('contrat');
            $type = (string) $this->input('type');
            $dateDebut = (string) $this->input('date_debut');
            $dateFin = $this->input('date_fin');

            if (! $source->agent) {
                $validator->errors()->add('contrat', 'L’agent de ce contrat a été supprimé.');

                return;
            }

            if ($source->avenants()->exists()) {
                $validator->errors()->add('contrat', 'Ce contrat a déjà été renouvelé. Renouvelez le contrat le plus récent.');

                return;
            }

            if (! Carbon::parse($dateDebut)->startOfDay()->gt($source->date_debut)) {
                $validator->errors()->add('date_debut', 'Le renouvellement doit débuter après le début du contrat actuel.');
            }

            if ($message = ReglesTypeContrat::validateDateFin($type, $dateDebut, $dateFin)) {
                $validator->errors()->add('date_fin', $message);
            }

            if ($message = ReglesTypeContrat::validatePeriodeEssai($type, $this->input('periode_essai_mois'))) {
                $validator->errors()->add('periode_essai_mois', $message);
            }

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if (Contrat::hasActiveOverlap(
                (string) $source->agent_id,
                $dateDebut,
                blank($dateFin) ? null : (string) $dateFin,
                (string) $source->id,
            )) {
                $validator->errors()->add(
                    'date_debut',
                    'Un autre contrat actif de cet agent chevauche déjà cette période.',
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
