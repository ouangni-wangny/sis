<?php

namespace App\Application\Paie;

use App\Application\Reporting\PointageVacationsAction;
use App\Domain\Paie\CalculRemunerationCi;
use App\Domain\Shared\Enums\UnitePrime;
use App\Models\Agent;
use App\Models\PeriodePaie;
use App\Models\Vacation;

/**
 * Primes des qualifications de chaque agent (Paramètres → Qualifications), chiffrées pour une
 * période de paie :
 * - valeur « par mois » : montant fixe ;
 * - valeur « par jour » : montant × jours pointés présents sur le mois de
 *   pointage (du 26 du mois précédent au 25 du mois de paie), comme la
 *   feuille de pointage. Une double vacation compte pour deux jours.
 *
 * Seules les qualifications et les primes actives sont retenues.
 */
final class PrimesAgentsPeriode
{
    /** @var array<string, list<array<string, mixed>>> */
    private array $lignes = [];

    /** @var array<string, int> */
    private array $jours = [];

    public function __construct(private readonly PointageVacationsAction $pointage) {}

    /** @param  list<string>  $agentIds */
    public function charger(PeriodePaie $periode, array $agentIds): self
    {
        $this->lignes = [];
        $this->jours = [];
        if ($agentIds === []) {
            return $this;
        }

        $agents = Agent::query()
            ->whereIn('id', $agentIds)
            ->with(['qualifications' => fn ($q) => $q->where('actif', true)->with([
                'primes' => fn ($b) => $b->where('actif', true),
            ])])
            ->get(['id']);

        $avecPrimeJour = $agents
            ->filter(fn (Agent $a) => $a->qualifications->contains(
                fn ($t) => $t->primes->contains(fn ($b) => $b->unite === UnitePrime::Jour),
            ))
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();
        [$debut, $fin] = self::moisPointage($periode);
        $this->jours = $this->joursPointes($avecPrimeJour, $debut, $fin);

        foreach ($agents as $agent) {
            foreach ($agent->qualifications->sortBy('libelle') as $type) {
                foreach ($type->primes->sortBy(fn ($b) => $b->unite->value) as $bareme) {
                    $unitaire = CalculRemunerationCi::money($bareme->montant);
                    $quantite = $bareme->unite === UnitePrime::Jour ? ($this->jours[(string) $agent->id] ?? 0) : 1;
                    $this->lignes[(string) $agent->id][] = [
                        'abreviation' => $type->abreviation,
                        'libelle' => $type->libelle,
                        'unite' => $bareme->unite->value,
                        'montant_unitaire' => $unitaire,
                        'quantite' => $quantite,
                        'montant' => round($unitaire * $quantite, 2),
                    ];
                }
            }
        }

        return $this;
    }

    /** @return list<array{abreviation: string, libelle: string, unite: string, montant_unitaire: float, quantite: int, montant: float}> */
    public function lignes(string $agentId): array
    {
        return $this->lignes[$agentId] ?? [];
    }

    public function total(string $agentId): float
    {
        return round(array_sum(array_column($this->lignes($agentId), 'montant')), 2);
    }

    public function jours(string $agentId): ?int
    {
        return $this->jours[$agentId] ?? null;
    }

    /**
     * Mois de pointage d’une période de paie : du 26 du mois précédent au 25.
     *
     * @return array{0: string, 1: string}
     */
    public static function moisPointage(PeriodePaie $periode): array
    {
        $fin = $periode->date_debut->copy()->startOfMonth()->day(25);

        return [$fin->copy()->subMonthNoOverflow()->day(26)->toDateString(), $fin->toDateString()];
    }

    /**
     * @param  list<string>  $agentIds
     * @return array<string, int>
     */
    private function joursPointes(array $agentIds, string $debut, string $fin): array
    {
        if ($agentIds === []) {
            return [];
        }

        $vacations = Vacation::query()
            ->whereIn('agent_id', $agentIds)
            ->whereDate('date_debut', '<=', $fin)
            ->where(fn ($q) => $q->whereNull('date_fin')->orWhereDate('date_fin', '>=', $debut))
            ->get();

        $jours = array_fill_keys($agentIds, 0);
        foreach ($this->pointage->execute($vacations, $debut, $fin) as $ligne) {
            if ($ligne['controle'] === PointageVacationsAction::PRESENT) {
                $jours[(string) $ligne['vacation']->agent_id]++;
            }
        }

        return $jours;
    }
}
