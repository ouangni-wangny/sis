<?php

namespace App\Application\Operation;

use App\Domain\Shared\Enums\StatutAbsence;
use App\Domain\Shared\Enums\StatutAgent;
use App\Domain\Shared\Enums\StatutVacation;
use App\Domain\Shared\Exceptions\DomainException;
use App\Domain\Shared\Support\ShiftInterval;
use App\Domain\Shared\Support\ShiftSchedule;
use App\Models\Absence;
use App\Models\Poste;
use App\Models\Vacation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Garde le planning de chaque poste rempli à l’avance : tant qu’un poste est
 * planifié, son rythme récent (titulaires, jours, horaires) est reconduit.
 *
 * Rejouable et non bloquant : ce qui existe déjà n’est pas touché, et un agent
 * qui ne peut plus être affecté (contrat fini, repos…) laisse la case vide.
 */
final class ProlongerPlanningPostesAction
{
    /** Historique lu pour retrouver le rythme du poste. */
    private const HISTORIQUE_JOURS = 56;

    private const MIN_JOURS_PLANIFIES = 7;

    /** Un agent absent aujourd’hui peut reprendre à la date reconduite. */
    private const STATUTS_RECONDUCTIBLES = [
        StatutAgent::Disponible,
        StatutAgent::EnActivite,
        StatutAgent::Conge,
        StatutAgent::Malade,
    ];

    public function __construct(
        private readonly CreateVacationAction $create,
    ) {}

    /**
     * @param  bool  $reprendre  Relance aussi les postes dont le planning s’est arrêté il y a moins d’un mois.
     * @param  int|null  $dureeMaxSecondes  S’arrête entre deux postes passé ce délai (postes_restants > 0).
     * @return array{postes: int, created: int, skipped: int, postes_restants: int, errors: list<string>}
     */
    public function execute(?string $aujourdhui = null, bool $reprendre = false, ?int $dureeMaxSecondes = null): array
    {
        $debut = microtime(true);
        $today = ShiftSchedule::dayNumber($aujourdhui ?? now()->toDateString());
        $horizon = $today + (int) config('sis.vacation.horizon_planning_jours', 42);
        $stats = ['postes' => 0, 'created' => 0, 'skipped' => 0, 'postes_restants' => 0, 'errors' => []];

        $postes = Poste::query()
            ->where('planning_continu', true)
            ->whereHas('site', fn ($s) => $s->where('interne', false)
                ->whereHas('client', fn ($c) => $c->where('statut', 'actif')))
            ->get();

        foreach ($postes->values() as $i => $poste) {
            if ($dureeMaxSecondes !== null && microtime(true) - $debut >= $dureeMaxSecondes) {
                $stats['postes_restants'] = $postes->count() - $i;
                break;
            }

            try {
                $resultat = $this->prolonger($poste, $today, $horizon, $reprendre);
            } catch (\Throwable $e) {
                Log::error('Reconduction du planning échouée', ['poste_id' => $poste->id, 'message' => $e->getMessage()]);
                $stats['errors'][] = "{$poste->nom}: {$e->getMessage()}";

                continue;
            }

            if ($resultat === null) {
                continue;
            }

            $stats['postes']++;
            $stats['created'] += $resultat['created'];
            $stats['skipped'] += $resultat['skipped'];
            array_push($stats['errors'], ...$resultat['errors']);
        }

        return $stats;
    }

    /** @return array{created: int, skipped: int, errors: list<string>}|null */
    private function prolonger(Poste $poste, int $today, int $horizon, bool $reprendre): ?array
    {
        $parJour = $this->rythme($poste);
        if ($parJour->isEmpty()) {
            return null;
        }

        $dernier = (int) $parJour->keys()->max();
        $arrete = $dernier < $today;

        // Planning retiré exprès ou arrêté depuis longtemps : on ne le ressuscite pas seul.
        if ($arrete && (! $reprendre || $dernier < $today - 30)) {
            return null;
        }
        if ($dernier >= $horizon) {
            return null;
        }

        $cycle = $this->cycle($parJour, $dernier);
        if ($cycle === null) {
            return null;
        }

        $resultat = ['created' => 0, 'skipped' => 0, 'errors' => []];

        for ($jour = max($dernier + 1, $today); $jour <= $horizon; $jour++) {
            // Les cycles font 7 × n jours : le jour de la semaine est conservé.
            $source = $dernier - $cycle + 1 + (($jour - $dernier - 1) % $cycle);

            foreach ($parJour->get($source, collect()) as $modele) {
                $this->reconduire($modele, ShiftSchedule::isoFromDayNumber($jour), $resultat);
            }
        }

        return $resultat;
    }

    /**
     * Créneaux des titulaires par jour : un remplacement ponctuel ou des heures
     * supplémentaires ne deviennent pas le nouveau planning du poste. Une vacation
     * sur période compte pour chacun de ses jours ; une affectation sans fin couvre
     * déjà l’avenir et n’a rien à reconduire.
     *
     * @return Collection<int, Collection<int, Vacation>>
     */
    private function rythme(Poste $poste): Collection
    {
        $vacations = Vacation::query()
            ->where('poste_id', $poste->id)
            ->where('est_remplacement', false)
            ->where('heures_supplementaires', false)
            ->whereNotNull('date_fin')
            ->where(fn ($q) => $q->where('statut', '!=', StatutVacation::Annulee->value)->orWhereNotNull('absence_id'))
            ->get();

        $parJour = [];
        foreach ($vacations as $vacation) {
            $schedule = ShiftSchedule::fromVacation($vacation);
            for ($jour = $schedule->firstDay; $jour <= $schedule->lastDay; $jour++) {
                $parJour[$jour][] = $vacation;
            }
        }
        if ($parJour === []) {
            return collect();
        }

        $limite = max(array_keys($parJour)) - self::HISTORIQUE_JOURS + 1;

        return collect($parJour)
            ->filter(fn ($liste, $jour) => $jour >= $limite)
            ->map(fn (array $liste) => collect($liste));
    }

    /**
     * Plus petit cycle confirmé par la semaine ou le cycle qui précède (3 semaines
     * A, B, A donnent un cycle de 2 semaines). Sinon tout l’historique disponible,
     * jusqu’à 4 semaines.
     *
     * @param  Collection<int, Collection<int, Vacation>>  $parJour
     */
    private function cycle(Collection $parJour, int $dernier): ?int
    {
        $premier = (int) $parJour->keys()->min();
        $etendue = $dernier - $premier + 1;
        if ($etendue < self::MIN_JOURS_PLANIFIES) {
            return null;
        }

        $signature = fn (int $jour): array => $parJour->get($jour, collect())
            ->map(fn (Vacation $v) => $v->agent_id.'|'.$v->heure_debut.'|'.$v->heure_fin)
            ->sort()
            ->values()
            ->all();

        foreach ([7, 14, 21, 28] as $longueur) {
            if ($etendue < $longueur + 7) {
                continue;
            }

            // Seul le cycle le plus récent compte : après une replanification, l’ancien
            // rythme encore dans l’historique ne doit pas revenir.
            $identique = true;
            $limite = max($premier + $longueur, $dernier - $longueur + 1);
            for ($jour = $dernier; $jour >= $limite; $jour--) {
                if ($signature($jour) !== $signature($jour - $longueur)) {
                    $identique = false;
                    break;
                }
            }

            if ($identique) {
                return $longueur;
            }
        }

        return min(28, intdiv($etendue, 7) * 7);
    }

    /** @param  array{created: int, skipped: int, errors: list<string>}  $resultat */
    private function reconduire(Vacation $modele, string $date, array &$resultat): void
    {
        $existe = Vacation::query()
            ->where('agent_id', $modele->agent_id)
            ->where('poste_id', $modele->poste_id)
            ->whereDate('date_debut', $date)
            ->where('heure_debut', $modele->heure_debut)
            ->where('statut', '!=', StatutVacation::Annulee->value)
            ->exists();

        if ($existe) {
            return;
        }

        $base = [
            'agent_id' => $modele->agent_id,
            'site_id' => $modele->site_id,
            'poste_id' => $modele->poste_id,
            'heure_debut' => substr((string) $modele->heure_debut, 0, 5),
            'heure_fin' => substr((string) $modele->heure_fin, 0, 5),
            'reconduite' => true,
        ];

        // Absence déjà approuvée ce jour-là : la case est à recouvrir, pas à remplir.
        $absence = Absence::query()
            ->where('agent_id', $modele->agent_id)
            ->where('statut', StatutAbsence::Approuvee->value)
            ->whereDate('date_debut', '<=', $date)
            ->where(fn ($q) => $q->whereNull('date_fin')->orWhereDate('date_fin', '>=', $date))
            ->first();

        if ($absence) {
            Vacation::query()->create([
                ...$base,
                'date_debut' => $date,
                'date_fin' => ShiftInterval::needsNextCalendarDay((string) $modele->heure_debut, (string) $modele->heure_fin)
                    ? ShiftSchedule::isoFromDayNumber(ShiftSchedule::dayNumber($date) + 1)
                    : $date,
                'statut' => StatutVacation::ARecouvrir->value,
                'absence_id' => $absence->id,
            ]);
            $resultat['created']++;

            return;
        }

        try {
            $this->create->execute([
                ...$base,
                'date_debut' => $date,
                'date_fin' => $date,
                'statuts_autorises' => self::STATUTS_RECONDUCTIBLES,
            ]);
            $resultat['created']++;
        } catch (DomainException|ValidationException $e) {
            $resultat['skipped']++;
            $resultat['errors'][] = "{$date} {$modele->agent_id}: {$e->getMessage()}";
        }
    }
}
