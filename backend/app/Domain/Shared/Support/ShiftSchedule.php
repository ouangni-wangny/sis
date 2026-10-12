<?php

namespace App\Domain\Shared\Support;

use App\Models\Vacation;
use Carbon\Carbon;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Créneaux journaliers d’une vacation (une prise de poste par jour).
 *
 * Une vacation peut couvrir une période (date_debut → date_fin) : on la
 * traite comme une suite de créneaux identiques, un par jour de prise de
 * poste — et non comme un bloc continu. Sinon un agent planifié de jour
 * sur un mois serait « occupé » aussi toutes les nuits, et une nuit sur
 * période ne s’afficherait que le premier jour.
 *
 * Jours de prise de poste (date_fin stockée = fin calendaire du dernier créneau) :
 * - jour  : date_debut … date_fin
 * - nuit / cycle 24h : date_debut … date_fin − 1 (le dernier créneau finit le matin de date_fin)
 * - date_fin null : affectation continue (horizon OPEN_ENDED_HORIZON_YEARS)
 *
 * Calculs en minutes entières (pas de Carbon dans les boucles).
 */
final class ShiftSchedule
{
    public const OPEN_ENDED_HORIZON_YEARS = 5;

    private const MINUTES_PER_DAY = 1440;

    private function __construct(
        public readonly int $firstDay,
        public readonly int $lastDay,
        public readonly int $startMinute,
        public readonly int $spanMinutes,
    ) {}

    /** Depuis des valeurs stockées (date_fin = fin calendaire du dernier créneau). */
    public static function fromStored(
        string $dateDebut,
        string $heureDebut,
        string $heureFin,
        ?string $dateFin,
    ): self {
        $hd = ShiftInterval::hm($heureDebut);
        $hf = ShiftInterval::hm($heureFin);
        $first = self::dayNumber($dateDebut);

        if ($dateFin === null || $dateFin === '') {
            $last = self::dayNumber(
                Carbon::parse($dateDebut)->addYears(self::OPEN_ENDED_HORIZON_YEARS)->toDateString()
            );
        } else {
            $last = self::dayNumber($dateFin);
            if (ShiftInterval::needsNextCalendarDay($hd, $hf)) {
                $last--;
            }
        }

        return new self(
            $first,
            max($first, $last),
            self::minutesOfDay($hd),
            (int) round(ShiftInterval::durationHours($hd, $hf) * 60),
        );
    }

    public static function fromVacation(Vacation $v): self
    {
        return self::fromStored(
            $v->date_debut->format('Y-m-d'),
            (string) $v->heure_debut,
            (string) $v->heure_fin,
            $v->date_fin?->format('Y-m-d'),
        );
    }

    public function coversDay(string|int $day): bool
    {
        $n = is_int($day) ? $day : self::dayNumber($day);

        return $n >= $this->firstDay && $n <= $this->lastDay;
    }

    /** @return list<string> Y-m-d */
    public function days(): array
    {
        $out = [];
        for ($d = $this->firstDay; $d <= $this->lastDay; $d++) {
            $out[] = self::isoFromDayNumber($d);
        }

        return $out;
    }

    /** @return array{0: int, 1: int} minutes absolues [début, fin[ du créneau du jour $day */
    public function windowOnDay(int $day): array
    {
        $start = $day * self::MINUTES_PER_DAY + $this->startMinute;

        return [$start, $start + $this->spanMinutes];
    }

    /** Au moins un créneau de $this chevauche un créneau de $other. */
    public function overlaps(self $other): bool
    {
        foreach ($this->pairs($other, 1) as [$a, $b]) {
            if ($a[0] < $b[1] && $a[1] > $b[0]) {
                return true;
            }
        }

        return false;
    }

    /**
     * Plus petit écart (minutes) entre un créneau de $this et un créneau de $other,
     * sans chevauchement. null si aucun créneau proche (> 2 jours) ou en cas de chevauchement.
     *
     * @return array{gap: int, mine: array{0: int, 1: int}, other: array{0: int, 1: int}}|null
     */
    public function closestGap(self $other): ?array
    {
        $best = null;
        foreach ($this->pairs($other, 2) as [$a, $b]) {
            if ($a[0] < $b[1] && $a[1] > $b[0]) {
                return null;
            }
            $gap = $a[1] <= $b[0] ? $b[0] - $a[1] : $a[0] - $b[1];
            if ($best === null || $gap < $best['gap']) {
                $best = ['gap' => $gap, 'mine' => $a, 'other' => $b];
            }
        }

        return $best;
    }

    /**
     * Paires de créneaux voisins (± $reach jours) entre $this et $other.
     *
     * @return iterable<array{0: array{0: int, 1: int}, 1: array{0: int, 1: int}}>
     */
    private function pairs(self $other, int $reach): iterable
    {
        $from = max($this->firstDay, $other->firstDay - $reach);
        $to = min($this->lastDay, $other->lastDay + $reach);

        for ($d = $from; $d <= $to; $d++) {
            $mine = $this->windowOnDay($d);
            for ($k = $d - $reach; $k <= $d + $reach; $k++) {
                if ($other->coversDay($k)) {
                    yield [$mine, $other->windowOnDay($k)];
                }
            }
        }
    }

    public static function dayNumber(string $isoDate): int
    {
        $d = new DateTimeImmutable(substr($isoDate, 0, 10).' 00:00:00', new DateTimeZone('UTC'));

        return intdiv($d->getTimestamp(), 86400);
    }

    public static function isoFromDayNumber(int $day): string
    {
        return gmdate('Y-m-d', $day * 86400);
    }

    private static function minutesOfDay(string $hm): int
    {
        [$h, $m] = array_map('intval', explode(':', $hm));

        return $h * 60 + $m;
    }
}
