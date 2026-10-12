<?php

namespace App\Application\Paie;

use App\Domain\Paie\CalculRemunerationCi;
use App\Domain\Shared\Enums\StatutBulletinPaie;
use App\Models\BulletinPaie;
use App\Models\Contrat;
use App\Models\PeriodePaie;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class GenerateBulletinsPaieAction
{
    /** Contrats payables : un contrat terminé ou résilié reste dû pour ses jours travaillés. */
    private const STATUTS_PAYABLES = ['actif', 'termine', 'resilie'];

    /**
     * Génère (ou complète) les bulletins de la période.
     *
     * - Un bulletin par agent dont un contrat couvre au moins un jour de la
     *   période, quel que soit son statut actuel : un CDD ou stage terminé en
     *   cours de mois (contrat clôturé, agent archivé) doit encore être payé.
     * - Solde de tout compte : contrat terminé le mois précédant la période et
     *   jamais payé (aucun bulletin) — ex. fin au 31 août, paie de septembre.
     * - Relancer la génération ne touche pas aux bulletins déjà renseignés,
     *   validés ou payés : seuls les manquants et les brouillons vierges sont
     *   (re)calculés.
     *
     * @return int nombre de bulletins créés ou recalculés
     */
    public function execute(PeriodePaie $periode): int
    {
        if ($periode->statut->value === 'cloturee') {
            throw ValidationException::withMessages([
                'statut' => 'Cette période de paie est clôturée.',
            ]);
        }

        $count = 0;

        DB::transaction(function () use ($periode, &$count) {
            $existants = BulletinPaie::query()
                ->where('periode_paie_id', $periode->id)
                ->get()
                ->keyBy('agent_id');

            foreach ($this->contratsAPayer($periode) as [$contrat, $motif]) {
                $agent = $contrat->agent;
                $existant = $existants->get($agent->id);
                if ($existant && ! $this->recalculable($existant)) {
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
                            'contrat_fin' => $contrat->date_fin?->toDateString(),
                            // periode | fin_contrat (terminé dans la période) | solde_tout_compte
                            'motif' => $motif,
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

    /**
     * Contrat retenu par agent pour la période, avec le motif d’inclusion.
     *
     * @return list<array{0: Contrat, 1: string}>
     */
    private function contratsAPayer(PeriodePaie $periode): array
    {
        $debut = $periode->date_debut->toDateString();
        $fin = $periode->date_fin->toDateString();

        $retenus = [];

        // 1. Contrats couvrant au moins un jour de la période (le plus récent par agent).
        $couvrants = Contrat::query()
            ->whereIn('statut', self::STATUTS_PAYABLES)
            ->whereDate('date_debut', '<=', $fin)
            ->where(fn ($q) => $q->whereNull('date_fin')->orWhereDate('date_fin', '>=', $debut))
            ->whereHas('agent')
            ->with('agent')
            ->orderByDesc('date_debut')
            ->get();

        foreach ($couvrants as $contrat) {
            if (isset($retenus[$contrat->agent_id])) {
                continue;
            }
            $termineDansPeriode = $contrat->date_fin !== null
                && $contrat->date_fin->toDateString() <= $fin
                && $contrat->statut !== 'actif';
            $retenus[$contrat->agent_id] = [$contrat, $termineDansPeriode ? 'fin_contrat' : 'periode'];
        }

        // 2. Solde de tout compte : terminé le mois précédent, jamais payé.
        $moisPrecedent = $periode->date_debut->copy()->subMonthNoOverflow()->toDateString();
        $soldes = Contrat::query()
            ->whereIn('statut', self::STATUTS_PAYABLES)
            ->whereNotNull('date_fin')
            ->whereDate('date_fin', '>=', $moisPrecedent)
            ->whereDate('date_fin', '<', $debut)
            ->whereDoesntHave(
                'bulletinsPaie',
                fn ($q) => $q->where('periode_paie_id', '!=', $periode->id),
            )
            ->whereHas('agent')
            ->with('agent')
            ->orderByDesc('date_fin')
            ->get();

        foreach ($soldes as $contrat) {
            $retenus[$contrat->agent_id] ??= [$contrat, 'solde_tout_compte'];
        }

        return array_values($retenus);
    }

    /** Brouillon jamais renseigné : peut être recalculé sans perdre de saisie. */
    private function recalculable(BulletinPaie $bulletin): bool
    {
        $details = is_array($bulletin->details) ? $bulletin->details : [];

        return $bulletin->statut === StatutBulletinPaie::Brouillon
            && ! array_key_exists('salaire_percu', $details)
            && ! array_key_exists('salaire_renseigne_le', $details);
    }
}
