<?php

namespace App\Application\Operation;

use App\Models\PlanningSemaine;
use Illuminate\Validation\ValidationException;

final class DupliquerPlanningSemaineAction
{
    public function __construct(
        private readonly UpsertPlanningSemaineAction $upsert,
    ) {}

    /**
     * Copie les créneaux de la semaine source vers la cible (écrase la cible).
     *
     * @param  array{poste_id: string, annee_iso: int, semaine_iso: int, source_annee_iso?: int, source_semaine_iso?: int}  $data
     */
    public function execute(array $data): PlanningSemaine
    {
        $posteId = $data['poste_id'];
        $annee = (int) $data['annee_iso'];
        $semaine = (int) $data['semaine_iso'];

        // Par défaut : semaine ISO précédente.
        if (isset($data['source_annee_iso'], $data['source_semaine_iso'])) {
            $srcAnnee = (int) $data['source_annee_iso'];
            $srcSemaine = (int) $data['source_semaine_iso'];
        } else {
            $lundi = \Carbon\Carbon::now('Africa/Abidjan')
                ->setISODate($annee, $semaine)
                ->startOfWeek(\Carbon\Carbon::MONDAY)
                ->subWeek();
            $srcAnnee = (int) $lundi->isoWeekYear();
            $srcSemaine = (int) $lundi->isoWeek();
        }

        $source = PlanningSemaine::query()
            ->with('creneaux')
            ->where('poste_id', $posteId)
            ->where('annee_iso', $srcAnnee)
            ->where('semaine_iso', $srcSemaine)
            ->first();

        if (! $source) {
            throw ValidationException::withMessages([
                'source' => "Aucun planning trouvé pour la semaine {$srcSemaine}/{$srcAnnee}.",
            ]);
        }

        return $this->upsert->execute([
            'poste_id' => $posteId,
            'annee_iso' => $annee,
            'semaine_iso' => $semaine,
            'creneaux' => $source->creneaux->map(fn ($c) => [
                'agent_id' => $c->agent_id,
                'jour' => $c->jour,
                'heure_debut' => $c->heureDebutHm(),
                'heure_fin' => $c->heureFinHm(),
                'quart' => $c->quart,
            ])->all(),
        ]);
    }
}
