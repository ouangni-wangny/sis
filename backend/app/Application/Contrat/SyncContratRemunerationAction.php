<?php

namespace App\Application\Contrat;

use App\Domain\Paie\CalculRemunerationCi;
use App\Models\Agent;
use App\Models\Contrat;

final class SyncContratRemunerationAction
{
    /**
     * Enrichit les données contrat avec brut / parts IGR / CNPS / net.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function prepare(array $data, ?Agent $agent = null, ?Contrat $existing = null): array
    {
        $agent ??= isset($data['agent_id'])
            ? Agent::query()->find($data['agent_id'])
            : $existing?->agent;

        // Situation familiale = fiche Agent (source de vérité). Snapshot sur le contrat.
        unset($data['nombre_enfants']);
        $nombreEnfants = (int) ($agent?->nombre_enfants ?? 0);
        $situation = $agent?->situation_matrimoniale;

        // Si salaire_base absent mais ancien salaire_brut fourni → base = brut
        if (
            (! array_key_exists('salaire_base', $data) || $data['salaire_base'] === null)
            && isset($data['salaire_brut'])
            && $data['salaire_brut'] !== null
            && ! $this->hasAnyPrime($data)
        ) {
            $data['salaire_base'] = $data['salaire_brut'];
        }

        $calc = CalculRemunerationCi::calculer([
            'salaire_base' => $data['salaire_base'] ?? $existing?->salaire_base,
            'indemnite_fonction' => $data['indemnite_fonction'] ?? $existing?->indemnite_fonction,
            'prime_responsabilite' => $data['prime_responsabilite'] ?? $existing?->prime_responsabilite,
            'prime_transport' => $data['prime_transport'] ?? $existing?->prime_transport,
            'prime_entretien_tenue' => $data['prime_entretien_tenue'] ?? $existing?->prime_entretien_tenue,
            'sursalaire' => $data['sursalaire'] ?? $existing?->sursalaire,
            'nombre_enfants' => $nombreEnfants,
            'situation_matrimoniale' => $situation,
            // Override RH exceptionnel uniquement si fourni explicitement
            'parts_igr' => array_key_exists('parts_igr', $data) ? $data['parts_igr'] : null,
        ]);

        $data['nombre_enfants'] = $nombreEnfants;
        $data['salaire_brut'] = $calc['salaire_brut'];
        $data['parts_igr'] = $calc['parts_igr'];
        $data['montant_igr'] = $calc['montant_igr'];
        $data['retenue_cnps'] = $calc['retenue_cnps'];
        $data['salaire_net'] = $calc['salaire_net'];
        $data['salaire'] = $calc['salaire_brut'];

        return $data;
    }

    /** @param  array<string, mixed>  $data */
    private function hasAnyPrime(array $data): bool
    {
        foreach ([
            'indemnite_fonction',
            'prime_responsabilite',
            'prime_transport',
            'prime_entretien_tenue',
            'sursalaire',
        ] as $key) {
            if (! empty($data[$key]) && (float) $data[$key] > 0) {
                return true;
            }
        }

        return false;
    }
}
