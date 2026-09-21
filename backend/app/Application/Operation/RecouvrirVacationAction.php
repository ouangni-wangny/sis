<?php

namespace App\Application\Operation;

use App\Domain\Shared\Enums\StatutVacation;
use App\Models\Agent;
use App\Models\Vacation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Remplace une vacation « à recouvrir » par un autre agent
 * sur le même site / poste / créneau.
 */
final class RecouvrirVacationAction
{
    public function __construct(
        private CreateVacationAction $create,
    ) {}

    public function execute(Vacation $trou, string $remplacantId): Vacation
    {
        return DB::transaction(function () use ($trou, $remplacantId) {
            if ($trou->statut !== StatutVacation::ARecouvrir) {
                throw ValidationException::withMessages([
                    'vacation' => 'Seules les vacations « à recouvrir » peuvent être remplacées.',
                ]);
            }

            if ($remplacantId === $trou->agent_id) {
                throw ValidationException::withMessages([
                    'agent_id' => 'Choisissez un autre agent que celui déjà affecté.',
                ]);
            }

            $remplacant = Agent::query()->findOrFail($remplacantId);
            $this->annulerVacationPoolSiege($remplacant, $trou);

            $remplacement = $this->create->execute([
                'agent_id' => $remplacantId,
                'site_id' => $trou->site_id,
                'poste_id' => $trou->poste_id,
                'date_debut' => $trou->date_debut->format('Y-m-d'),
                'date_fin' => $trou->date_fin?->format('Y-m-d'),
                'heure_debut' => substr((string) $trou->heure_debut, 0, 5),
                'heure_fin' => substr((string) $trou->heure_fin, 0, 5),
            ]);

            $trou->update(['statut' => StatutVacation::Annulee->value]);

            return $remplacement->load(['agent', 'site', 'poste']);
        });
    }

    /**
     * Un agent du pool siège garde une vacation de présence sur son poste
     * siège chaque jour ; le déployer en remplacement ailleurs libère
     * automatiquement ce créneau pour la durée du recouvrement, plutôt que
     * de bloquer l’opération sur un conflit de vacation.
     */
    private function annulerVacationPoolSiege(Agent $remplacant, Vacation $trou): void
    {
        if (! $remplacant->poste_siege_id) {
            return;
        }

        $dateDebut = $trou->date_debut->toDateString();
        $dateFin = $trou->date_fin?->toDateString() ?? $dateDebut;

        Vacation::query()
            ->where('agent_id', $remplacant->id)
            ->where('poste_id', $remplacant->poste_siege_id)
            ->whereIn('statut', [StatutVacation::Planifiee->value, StatutVacation::EnCours->value])
            ->whereDate('date_debut', '<=', $dateFin)
            ->where(function ($q) use ($dateDebut) {
                $q->whereNull('date_fin')->orWhereDate('date_fin', '>=', $dateDebut);
            })
            ->update(['statut' => StatutVacation::Annulee->value]);
    }
}
