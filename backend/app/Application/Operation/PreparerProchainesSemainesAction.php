<?php

namespace App\Application\Operation;

use App\Domain\Shared\Enums\StatutPlanningSemaine;
use App\Models\PlanningSemaine;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Copie explicite de la dernière semaine publiée vers les N prochaines semaines
 * encore absentes ou en brouillon (jamais silencieuse / forcée).
 */
final class PreparerProchainesSemainesAction
{
    public function __construct(
        private readonly UpsertPlanningSemaineAction $upsert,
    ) {}

    /**
     * @return array{preparees: int, plannings: list<PlanningSemaine>}
     */
    public function execute(string $posteId, int $nombreSemaines = 4): array
    {
        if ($nombreSemaines < 1 || $nombreSemaines > 12) {
            throw ValidationException::withMessages([
                'nombre' => 'Indiquez entre 1 et 12 semaines à préparer.',
            ]);
        }

        return DB::transaction(function () use ($posteId, $nombreSemaines) {
            $source = PlanningSemaine::query()
                ->with('creneaux')
                ->where('poste_id', $posteId)
                ->where('statut', StatutPlanningSemaine::Publiee)
                ->orderByDesc('annee_iso')
                ->orderByDesc('semaine_iso')
                ->first();

            if (! $source || $source->creneaux->isEmpty()) {
                throw ValidationException::withMessages([
                    'source' => 'Publiez d’abord une semaine pour pouvoir préparer les suivantes.',
                ]);
            }

            $creneaux = $source->creneaux->map(fn ($c) => [
                'agent_id' => $c->agent_id,
                'jour' => $c->jour,
                'heure_debut' => $c->heureDebutHm(),
                'heure_fin' => $c->heureFinHm(),
                'quart' => $c->quart,
            ])->all();

            $cursor = $source->lundi()->copy()->addWeek();
            $prepared = [];

            for ($i = 0; $i < $nombreSemaines; $i++) {
                $annee = (int) $cursor->isoWeekYear();
                $semaine = (int) $cursor->isoWeek();

                $existant = PlanningSemaine::query()
                    ->where('poste_id', $posteId)
                    ->where('annee_iso', $annee)
                    ->where('semaine_iso', $semaine)
                    ->first();

                // Ne pas écraser une semaine déjà publiée.
                if ($existant?->statut === StatutPlanningSemaine::Publiee) {
                    $cursor->addWeek();

                    continue;
                }

                $prepared[] = $this->upsert->execute([
                    'poste_id' => $posteId,
                    'annee_iso' => $annee,
                    'semaine_iso' => $semaine,
                    'creneaux' => $creneaux,
                ]);

                $cursor->addWeek();
            }

            return [
                'preparees' => count($prepared),
                'plannings' => $prepared,
            ];
        });
    }
}
