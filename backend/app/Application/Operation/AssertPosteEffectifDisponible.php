<?php

namespace App\Application\Operation;

use App\Domain\Shared\Support\PosteEffectifRules;
use App\Models\Poste;
use App\Models\Vacation;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Refuse toute vacation qui ferait dépasser l’effectif du poste
 * (par jour / par quart). Aligné sur PosteEffectifRules + coverage.
 *
 * Invariant produit : un sur-effectif ne doit pas pouvoir être créé.
 */
final class AssertPosteEffectifDisponible
{
    public static function execute(
        ?string $posteId,
        string $dateDebut,
        string $heureDebut,
        string $heureFin,
        ?string $dateFin = null,
        ?string $excludeId = null,
        ?string $agentId = null,
    ): void {
        if (! $posteId) {
            return;
        }

        $poste = Poste::query()->find($posteId);
        if (! $poste || $poste->agents_requis < 1) {
            return;
        }

        $posteVacations = Vacation::query()
            ->where('poste_id', $poste->id)
            ->whereNotIn('statut', ['annulee', 'a_recouvrir'])
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->get();

        $reste = PosteEffectifRules::capaciteRestante(
            $poste,
            $posteVacations,
            $dateDebut,
            $heureDebut,
            $heureFin,
            $dateFin,
            $excludeId,
            $agentId,
        );

        if ($reste >= 1) {
            return;
        }

        $jour = Carbon::parse($dateDebut)->startOfDay();
        $windows = PosteEffectifRules::quartWindows($poste);
        $cap = $windows
            ? PosteEffectifRules::capaciteParQuartPourJour($poste, $jour, 'jour')
            : PosteEffectifRules::capaciteSimplePourJour($poste, $jour);

        $enPlace = $posteVacations->first(fn (Vacation $v) => substr((string) $v->date_debut, 0, 10) === substr($dateDebut, 0, 10)
            && substr((string) $v->heure_debut, 0, 5) === substr($heureDebut, 0, 5));

        throw ValidationException::withMessages([
            'poste_id' => sprintf(
                'Créneau du %s déjà couvert sur le poste « %s » (%s–%s)%s : capacité %d atteinte. Choisissez l’autre quart, un autre jour, ou remplacez l’agent déjà planifié.',
                $jour->format('d/m/Y'),
                $poste->nom,
                substr($heureDebut, 0, 5),
                substr($heureFin, 0, 5),
                $enPlace?->agent ? ' par '.$enPlace->agent->libelle() : '',
                $cap,
            ),
        ]);
    }
}
