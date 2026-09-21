<?php

namespace App\Domain\Shared\Support;

use App\Models\Poste;
use App\Models\Vacation;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Règles d’effectif poste — source unique pour Assert + Coverage.
 *
 * Invariant : on ne doit jamais pouvoir créer un créneau qui dépasse
 * la capacité du jour / du quart. Le statut « sur_effectif » n’est qu’un
 * diagnostic pour données héritées / seed, pas un état de planning normal.
 */
final class PosteEffectifRules
{
    /** Horizon pour les affectations sans date_fin (aligné conflits agent). */
    public const OPEN_ENDED_HORIZON_YEARS = 5;

    /**
     * Fenêtres jour / nuit du poste, ou null si pas de découpage enregistré.
     * Cycle 24h sans champs nuit = pas de quarts inventés : les agents
     * sont ensemble sur la même plage (capacité = agents_requis).
     *
     * @return array{jour: array{heure_debut: string, heure_fin: string}, nuit: array{heure_debut: string, heure_fin: string}}|null
     */
    public static function quartWindows(Poste $poste): ?array
    {
        if (! $poste->estCouverture24h()) {
            return null;
        }

        return [
            'jour' => [
                'heure_debut' => ShiftInterval::hm((string) $poste->heure_debut),
                'heure_fin' => ShiftInterval::hm((string) $poste->heure_fin),
            ],
            'nuit' => [
                'heure_debut' => ShiftInterval::hm((string) $poste->heure_debut_nuit),
                'heure_fin' => ShiftInterval::hm((string) $poste->heure_fin_nuit),
            ],
        ];
    }

    public static function capaciteParQuart(Poste $poste): int
    {
        if (self::quartWindows($poste) !== null) {
            return max(1, (int) ceil($poste->agents_requis / 2));
        }

        return max(1, (int) $poste->agents_requis);
    }

    public static function capaciteSimple(Poste $poste): int
    {
        if ($poste->alterne()) {
            return 1;
        }

        return max(1, (int) $poste->agents_requis);
    }

    /**
     * Jours de planning touchés par une vacation (même sémantique que coverage).
     *
     * @return list<string> Y-m-d
     */
    public static function planningDaysForShift(
        string $dateDebut,
        string $heureDebut,
        string $heureFin,
        ?string $dateFin,
    ): array {
        $debut = Carbon::parse($dateDebut)->startOfDay();
        $hd = ShiftInterval::hm($heureDebut);
        $hf = ShiftInterval::hm($heureFin);

        // Nuit / cycle : rattaché au seul jour de prise de poste.
        if (ShiftInterval::needsNextCalendarDay($hd, $hf)) {
            return [$debut->toDateString()];
        }

        if ($dateFin === null || $dateFin === '') {
            $fin = $debut->copy()->addYears(self::OPEN_ENDED_HORIZON_YEARS);
        } else {
            $fin = Carbon::parse($dateFin)->startOfDay();
        }

        $days = [];
        for ($d = $debut->copy(); $d->lte($fin); $d->addDay()) {
            $days[] = $d->toDateString();
        }

        return $days;
    }

    /** La vacation existante couvre-t-elle ce jour de planning ? */
    public static function vacationCoversPlanningDay(Vacation $v, Carbon $day): bool
    {
        $start = $v->date_debut->copy()->startOfDay();
        $hd = (string) $v->heure_debut;
        $hf = (string) $v->heure_fin;

        if (ShiftInterval::needsNextCalendarDay($hd, $hf)) {
            return $day->equalTo($start);
        }

        if ($v->date_fin === null) {
            $horizon = $start->copy()->addYears(self::OPEN_ENDED_HORIZON_YEARS);

            return $day->between($start, $horizon, true);
        }

        $end = $v->date_fin->copy()->startOfDay();

        return $day->between($start, $end, true);
    }

    /**
     * Quarts touchés par un créneau sur un jour donné ('jour'|'nuit'|'_simple').
     *
     * @return list<string>
     */
    public static function quartsTouched(
        Poste $poste,
        Carbon $day,
        string $heureDebut,
        string $heureFin,
    ): array {
        $windows = self::quartWindows($poste);
        if ($windows === null) {
            return ['_simple'];
        }

        [$vStart, $vEnd] = self::windowOnDate($day, $heureDebut, $heureFin);
        $touched = [];

        foreach (['jour', 'nuit'] as $key) {
            [$qStart, $qEnd] = self::windowOnDate(
                $day,
                $windows[$key]['heure_debut'],
                $windows[$key]['heure_fin'],
            );
            if ($vStart->lt($qEnd) && $vEnd->gt($qStart)) {
                $touched[] = $key;
            }
        }

        // Créneau hors fenêtres connues : traiter comme simple (sécurité).
        return $touched !== [] ? $touched : ['_simple'];
    }

    /**
     * Compte les vacations du poste déjà présentes sur un jour + quart.
     *
     * @param  Collection<int, Vacation>  $posteVacations
     */
    public static function countOnQuart(
        Collection $posteVacations,
        Poste $poste,
        Carbon $day,
        string $quart,
        ?string $excludeId = null,
    ): int {
        return $posteVacations
            ->filter(function (Vacation $v) use ($poste, $day, $quart, $excludeId) {
                if ($excludeId && (string) $v->id === (string) $excludeId) {
                    return false;
                }
                if (! self::vacationCoversPlanningDay($v, $day)) {
                    return false;
                }
                $touched = self::quartsTouched(
                    $poste,
                    $day,
                    (string) $v->heure_debut,
                    (string) $v->heure_fin,
                );

                return in_array($quart, $touched, true);
            })
            ->count();
    }

    /**
     * Capacité restante pour un nouveau créneau (min sur tous les jours/quarts touchés).
     * ≤ 0 ⇒ refus.
     *
     * @param  Collection<int, Vacation>  $posteVacations
     */
    public static function capaciteRestante(
        Poste $poste,
        Collection $posteVacations,
        string $dateDebut,
        string $heureDebut,
        string $heureFin,
        ?string $dateFin = null,
        ?string $excludeId = null,
    ): int {
        $days = self::planningDaysForShift($dateDebut, $heureDebut, $heureFin, $dateFin);
        $capQuart = self::capaciteParQuart($poste);
        $capSimple = self::capaciteSimple($poste);
        $reste = PHP_INT_MAX;

        foreach ($days as $dayIso) {
            $day = Carbon::parse($dayIso)->startOfDay();
            $quarts = self::quartsTouched($poste, $day, $heureDebut, $heureFin);

            foreach ($quarts as $quart) {
                $cap = $quart === '_simple' ? $capSimple : $capQuart;
                $deja = self::countOnQuart($posteVacations, $poste, $day, $quart, $excludeId);
                $reste = min($reste, $cap - $deja);
            }
        }

        return $reste === PHP_INT_MAX ? $capSimple : $reste;
    }

    /** @return array{0: Carbon, 1: Carbon} */
    public static function windowOnDate(Carbon $date, string $heureDebut, string $heureFin): array
    {
        $hd = ShiftInterval::hm($heureDebut);
        $hf = ShiftInterval::hm($heureFin);
        $start = $date->copy()->startOfDay()->setTimeFromTimeString($hd);
        $end = $date->copy()->startOfDay()->setTimeFromTimeString($hf);
        if (ShiftInterval::needsNextCalendarDay($hd, $hf) || $end->lte($start)) {
            $end = $end->copy()->addDay();
        }

        return [$start, $end];
    }
}
