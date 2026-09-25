<?php

namespace App\Application\Paie;

use App\Domain\Paie\CalculRemunerationCi;
use App\Domain\Shared\Enums\StatutBulletinPaie;
use App\Models\Agent;
use App\Models\BulletinPaie;
use App\Models\Contrat;
use App\Models\PeriodePaie;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class GenerateBulletinsPaieAction
{
    public function execute(PeriodePaie $periode): int
    {
        if ($periode->statut->value === 'cloturee') {
            throw ValidationException::withMessages([
                'statut' => 'Cette période de paie est clôturée.',
            ]);
        }

        $agents = Agent::query()
            ->whereHas('contratActif')
            ->with('contratActif')
            ->get();

        $count = 0;

        DB::transaction(function () use ($agents, $periode, &$count) {
            foreach ($agents as $agent) {
                $contrat = $agent->contratActif;
                if (! $contrat) {
                    continue;
                }

                $calc = CalculRemunerationCi::calculer([
                    'salaire_base' => $contrat->salaire_base
                        ?? $contrat->salaire_brut
                        ?? $contrat->salaire
                        ?? 0,
                    'indemnite_fonction' => $contrat->indemnite_fonction,
                    'prime_responsabilite' => $contrat->prime_responsabilite,
                    'prime_transport' => $contrat->prime_transport,
                    'prime_entretien_tenue' => $contrat->prime_entretien_tenue,
                    'sursalaire' => $contrat->sursalaire,
                    'situation_matrimoniale' => $agent->situation_matrimoniale?->value,
                    'nombre_enfants' => $agent->nombre_enfants,
                    'parts_igr' => $contrat->parts_igr,
                ]);

                $salaireBase = CalculRemunerationCi::money(
                    $contrat->salaire_base
                        ?? $contrat->salaire_brut
                        ?? $contrat->salaire
                        ?? 0
                );

                BulletinPaie::query()->updateOrCreate(
                    [
                        'periode_paie_id' => $periode->id,
                        'agent_id' => $agent->id,
                    ],
                    [
                        'contrat_id' => $contrat->id,
                        'salaire_brut' => $calc['salaire_brut'],
                        'retenue_cnps' => $calc['retenue_cnps'],
                        'montant_igr' => $calc['montant_igr'],
                        'salaire_net' => $calc['salaire_net'],
                        'statut' => StatutBulletinPaie::Brouillon->value,
                        'details' => [
                            'matricule' => $agent->matricule,
                            'agent' => trim($agent->prenom.' '.$agent->nom),
                            'contrat_reference' => $contrat->reference,
                            'contrat_type' => $contrat->type instanceof \BackedEnum
                                ? $contrat->type->value
                                : (string) $contrat->type,
                            'parts_igr' => $calc['parts_igr'],
                            'cnps_taux' => CalculRemunerationCi::CNPS_SALARIE_TAUX,
                            'rubriques' => [
                                'salaire_base' => $salaireBase,
                                'indemnite_fonction' => CalculRemunerationCi::money($contrat->indemnite_fonction),
                                'prime_responsabilite' => CalculRemunerationCi::money($contrat->prime_responsabilite),
                                'prime_transport' => CalculRemunerationCi::money($contrat->prime_transport),
                                'prime_entretien_tenue' => CalculRemunerationCi::money($contrat->prime_entretien_tenue),
                                'sursalaire' => CalculRemunerationCi::money($contrat->sursalaire),
                            ],
                        ],
                    ],
                );

                $count++;
            }
        });

        return $count;
    }
}
