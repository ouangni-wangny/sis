<?php

namespace App\Application\Operation;

use App\Application\Planning\RetirerRondierAction;
use App\Domain\Shared\Enums\StatutPlanningSemaine;
use App\Domain\Shared\Enums\StatutRonde;
use App\Domain\Shared\Enums\StatutVacation;
use App\Models\PlanningSemaine;
use App\Models\PlanningSemaineCreneau;
use App\Models\Poste;
use App\Models\Ronde;
use App\Models\Vacation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Annule le planning d’un poste pour une semaine ISO, pour le refaire —
 * seulement à partir d’aujourd’hui : les jours déjà passés (et leur pointage)
 * restent intacts.
 *
 * - vacations prévues du jour et des jours suivants : supprimées ;
 * - créneaux du modèle semaine à partir d’aujourd’hui : retirés ; le modèle
 *   disparaît s’il n’en reste plus, sinon il repasse en brouillon (à republier).
 *
 * Mêmes règles que la republication : remplacements d’absence, heures
 * supplémentaires et vacations liées à une ronde terminée sont conservés ; les
 * rondiers placés sur ces jours sont retirés ; refus si une ronde liée est
 * encore planifiée ou en cours.
 */
final class AnnulerPlanningSemaineAction
{
    /**
     * @return array{vacations_supprimees: int, creneaux_supprimes: int, modele_supprime: bool, a_partir_du: string}
     */
    public function execute(Poste $poste, int $anneeIso, int $semaineIso): array
    {
        // Semaine 53 seulement les années qui en ont une (sinon Carbon bascule
        // sur la semaine 1 de l’année suivante et annulerait la mauvaise semaine).
        $semainesDansAnnee = (int) Carbon::create($anneeIso, 12, 28)->isoWeek();
        if ($semaineIso < 1 || $semaineIso > $semainesDansAnnee) {
            throw ValidationException::withMessages([
                'semaine_iso' => "L’année {$anneeIso} compte {$semainesDansAnnee} semaines.",
            ]);
        }

        return DB::transaction(function () use ($poste, $anneeIso, $semaineIso) {
            $planning = PlanningSemaine::query()
                ->with('creneaux')
                ->where('poste_id', $poste->id)
                ->where('annee_iso', $anneeIso)
                ->where('semaine_iso', $semaineIso)
                ->lockForUpdate()
                ->first();

            $reference = $planning ?? new PlanningSemaine([
                'annee_iso' => $anneeIso,
                'semaine_iso' => $semaineIso,
            ]);
            $lundi = $reference->lundi()->toDateString();
            $dimanche = $reference->dimanche()->toDateString();
            $aujourdhui = Carbon::now('Africa/Abidjan')->toDateString();

            if ($dimanche < $aujourdhui) {
                throw ValidationException::withMessages([
                    'planning' => 'Cette semaine est passée : seuls les jours à venir peuvent être annulés.',
                ]);
            }
            $du = max($lundi, $aujourdhui);

            $vacations = self::supprimerPrevu(
                $poste,
                $du,
                $dimanche,
                'Impossible d’annuler : une ronde liée est encore planifiée ou en cours.',
            );

            // Rondiers placés sur ce poste (repos, place vide, absence) : ils
            // dépendaient de l’ancien planning, on les retire aussi — sinon ils
            // occupent la place et bloquent la nouvelle publication. Une absence
            // qu’ils recouvraient redevient « à recouvrir ».
            $rondiers = Vacation::query()
                ->with('agent.grade')
                ->where('poste_id', $poste->id)
                ->where('est_remplacement', true)
                ->where('statut', StatutVacation::Planifiee->value)
                ->whereDate('date_debut', '>=', $du)
                ->whereDate('date_debut', '<=', $dimanche)
                ->get()
                ->filter(fn (Vacation $v) => $v->agent?->estRondier());
            foreach ($rondiers as $vacation) {
                app(RetirerRondierAction::class)->execute($vacation);
                $vacations++;
            }
            $planning?->load('creneaux');

            $creneaux = 0;
            $modeleSupprime = false;
            if ($planning) {
                $aRetirer = $planning->creneaux
                    ->filter(fn (PlanningSemaineCreneau $c) => $c->datePourSemaine($planning) >= $du);
                $creneaux = $aRetirer->count();
                if ($creneaux === $planning->creneaux->count()) {
                    $planning->delete();
                    $modeleSupprime = true;
                } elseif ($creneaux > 0) {
                    PlanningSemaineCreneau::query()->whereIn('id', $aRetirer->pluck('id'))->delete();
                    // Semaine incomplète : repasse en brouillon pour que la grille et
                    // la couverture montrent les jours annulés « à planifier » (et non
                    // « repos » / effectif 0), jusqu’à la prochaine publication.
                    $planning->update(['statut' => StatutPlanningSemaine::Brouillon]);
                }
            }

            return [
                'vacations_supprimees' => $vacations,
                'creneaux_supprimes' => $creneaux,
                'modele_supprime' => $modeleSupprime,
                'a_partir_du' => $du,
            ];
        });
    }

    /**
     * Supprime les vacations prévues (hors remplacements / HS / rondes terminées)
     * du poste entre deux dates. À appeler dans une transaction.
     */
    private static function supprimerPrevu(Poste $poste, string $du, string $au, string $messageRondeOuverte): int
    {
        $ids = Vacation::query()
            ->where('poste_id', $poste->id)
            ->where('est_remplacement', false)
            ->where('heures_supplementaires', false)
            // Absence déjà recouverte : sa trace reste (sinon la republication
            // la recréerait « à recouvrir » à côté du remplaçant).
            ->where('remplacee', false)
            ->where(fn ($q) => $q
                ->where('statut', StatutVacation::Planifiee->value)
                ->orWhere('statut', StatutVacation::Annulee->value)
                ->orWhere(fn ($t) => $t
                    ->where('statut', StatutVacation::ARecouvrir->value)
                    ->whereNotNull('absence_id')))
            ->whereDate('date_debut', '>=', $du)
            ->whereDate('date_debut', '<=', $au)
            ->lockForUpdate()
            ->pluck('id')
            ->all();

        if ($ids === []) {
            return 0;
        }

        $hasOpenRonde = Ronde::query()
            ->whereIn('vacation_id', $ids)
            ->whereIn('statut', [StatutRonde::Planifiee, StatutRonde::EnCours])
            ->exists();

        if ($hasOpenRonde) {
            throw ValidationException::withMessages(['planning' => $messageRondeOuverte]);
        }

        $protegees = Ronde::query()
            ->whereIn('vacation_id', $ids)
            ->where('statut', StatutRonde::Terminee)
            ->pluck('vacation_id')
            ->all();

        $aSupprimer = array_values(array_diff($ids, $protegees));
        if ($aSupprimer === []) {
            return 0;
        }

        // Une par une : déclenche l’audit et le soft delete du modèle.
        $n = 0;
        foreach (Vacation::query()->whereIn('id', $aSupprimer)->get() as $vacation) {
            $vacation->delete();
            $n++;
        }

        return $n;
    }
}
