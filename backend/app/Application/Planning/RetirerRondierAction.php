<?php

namespace App\Application\Planning;

use App\Domain\Shared\Enums\StatutRonde;
use App\Domain\Shared\Enums\StatutVacation;
use App\Domain\Shared\Support\ShiftInterval;
use App\Domain\Shared\Support\ShiftSchedule;
use App\Models\PlanningSemaine;
use App\Models\Ronde;
use App\Models\Vacation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Retire un rondier d’un poste (erreur de planification) : supprime sa vacation
 * et son créneau dans le modèle de la semaine. La place redevient un trou.
 */
final class RetirerRondierAction
{
    public function execute(Vacation $vacation): void
    {
        $vacation->loadMissing('agent.grade');
        if (! $vacation->agent?->estRondier()) {
            throw ValidationException::withMessages([
                'vacation' => 'Cette vacation n’est pas celle d’un rondier.',
            ]);
        }

        $date = $vacation->date_debut->toDateString();
        if ($date < Carbon::now('Africa/Abidjan')->toDateString()) {
            throw ValidationException::withMessages([
                'vacation' => 'Une vacation passée ne peut plus être retirée (pointage conservé).',
            ]);
        }
        if ($vacation->statut !== StatutVacation::Planifiee) {
            throw ValidationException::withMessages([
                'vacation' => 'Seule une vacation encore prévue peut être retirée.',
            ]);
        }
        if (Ronde::query()->where('vacation_id', $vacation->id)
            ->whereIn('statut', [StatutRonde::Planifiee, StatutRonde::EnCours])->exists()) {
            throw ValidationException::withMessages([
                'vacation' => 'Impossible de retirer : une ronde liée est encore planifiée ou en cours.',
            ]);
        }

        DB::transaction(function () use ($vacation, $date) {
            if ($vacation->poste_id) {
                $iso = PlanningSemaine::isoFromDate($date);
                $planning = PlanningSemaine::query()
                    ->where('poste_id', $vacation->poste_id)
                    ->where('annee_iso', $iso['annee_iso'])
                    ->where('semaine_iso', $iso['semaine_iso'])
                    ->first();
                $planning?->creneaux()
                    ->where('agent_id', $vacation->agent_id)
                    ->where('jour', (int) Carbon::parse($date)->dayOfWeekIso)
                    ->get()
                    ->filter(fn ($c) => $c->heureDebutHm() === ShiftInterval::hm((string) $vacation->heure_debut))
                    ->each->delete();
            }

            // Le rondier recouvrait une absence : la case redevient « à recouvrir ».
            // Seulement s’il reste plus d’absences recouvertes que de remplaçants
            // sur ce créneau (sinon l’absence est tenue par quelqu’un d’autre, ou le
            // rondier couvrait un repos / une place vide).
            if ($vacation->est_remplacement && $vacation->poste_id) {
                $jour = Carbon::parse($date)->startOfDay();
                $memeCreneau = fn (Vacation $v) => ShiftInterval::hm((string) $v->heure_debut) === ShiftInterval::hm((string) $vacation->heure_debut)
                    && ShiftSchedule::fromVacation($v)->coversDay($date);
                $proches = Vacation::query()
                    ->where('poste_id', $vacation->poste_id)
                    ->where('id', '!=', $vacation->id)
                    ->whereDate('date_debut', '<=', $date)
                    ->where(fn ($q) => $q->whereNull('date_fin')
                        ->orWhereDate('date_fin', '>=', $jour->copy()->subDay()->toDateString()))
                    ->get()
                    ->filter($memeCreneau);
                $trous = $proches->filter(fn (Vacation $v) => $v->statut === StatutVacation::Annulee && $v->remplacee);
                $autresRemplacants = $proches->filter(fn (Vacation $v) => $v->est_remplacement
                    && ! in_array($v->statut, [StatutVacation::Annulee, StatutVacation::ARecouvrir], true));
                if ($trous->count() > $autresRemplacants->count()) {
                    $trous->first()->update(['statut' => StatutVacation::ARecouvrir->value, 'remplacee' => false]);
                }
            }

            $vacation->delete();
        });
    }
}
