<?php

namespace App\Http\Requests\Facture;

use App\Domain\Shared\Enums\PeriodiciteFacturation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProformaFactureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_id' => ['nullable', 'uuid', 'exists:clients,id'],
            'client_nom' => ['nullable', 'string', 'max:255'],
            'client_adresse' => ['nullable', 'string', 'max:1000'],
            'client_telephone' => ['nullable', 'string', 'max:50'],
            'client_email' => ['nullable', 'email', 'max:255'],
            'abonnement_id' => ['nullable', 'uuid', 'exists:abonnements,id'],
            'creer_abonnement' => ['nullable', 'boolean'],
            'offre_id' => ['nullable', 'uuid', 'exists:offres,id'],
            'offre_ids' => ['nullable', 'array', 'min:1'],
            'offre_ids.*' => ['uuid', 'exists:offres,id'],
            'site_id' => ['nullable', 'uuid', 'exists:sites,id'],
            'periodicite' => ['nullable', Rule::enum(PeriodiciteFacturation::class)],
            'date_debut_service' => ['nullable', 'date'],
            'date_fin_service' => ['nullable', 'date', 'after_or_equal:date_debut_service'],
            'delai_paiement_jours' => ['nullable', 'integer', Rule::in([0, 15, 30, 45, 60])],
            'notes' => ['nullable', 'string', 'max:2000'],
            'conditions_paiement' => ['nullable', 'string', 'max:500'],
            'delai_validite' => ['nullable', 'string', 'max:100'],
            'duree_contrat_min' => ['nullable', 'string', 'max:500'],
            'signataire_nom' => ['nullable', 'string', 'max:255'],
            'signataire_fonction' => ['nullable', 'string', 'max:255'],
            'lignes' => ['required', 'array', 'min:1'],
            'lignes.*.offre_id' => ['nullable', 'uuid', 'exists:offres,id'],
            'lignes.*.code_article' => ['nullable', 'string', 'max:50'],
            'lignes.*.description' => ['required', 'string', 'max:500'],
            'lignes.*.quantite' => ['required', 'numeric', 'min:0.01'],
            'lignes.*.prix_unitaire' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $clientId = $this->input('client_id');
            $clientNom = trim((string) $this->input('client_nom', ''));

            if (! filled($clientId) && $clientNom === '') {
                $validator->errors()->add(
                    'client_id',
                    'Sélectionnez un client du système ou saisissez le nom du destinataire.',
                );
                $validator->errors()->add(
                    'client_nom',
                    'Saisissez le nom du destinataire si ce n’est pas un client du système.',
                );
            }

            if (! filled($clientId) && filled($this->input('abonnement_id'))) {
                $validator->errors()->add(
                    'abonnement_id',
                    'Un abonnement ne peut être lié qu’à un client du système.',
                );
            }

            if (! filled($clientId) && ! empty($this->input('creer_abonnement'))) {
                $validator->errors()->add(
                    'creer_abonnement',
                    'Impossible de créer un abonnement sans client du système.',
                );
            }
        });
    }
}
