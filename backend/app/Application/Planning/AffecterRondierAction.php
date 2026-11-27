<?php

namespace App\Application\Planning;

use App\Application\Operation\CreateVacationAction;
use App\Application\Operation\RecouvrirVacationAction;
use App\Domain\Shared\Enums\StatutVacation;
use App\Domain\Shared\Support\PosteEffectifRules;
use App\Domain\Shared\Support\ShiftInterval;
use App\Models\Agent;
use App\Models\PlanningSemaine;
use App\Models\PlanningSemaineCreneau;
use App\Models\Poste;
use App\Models\Vacation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Place un rondier sur un poste un jour donné (repos d’un titulaire, place vide).
 *
 * Le créneau est ajouté au modèle de la semaine du poste (s’il existe) : le
 * rondier fait partie du planning officiel du jour, la couverture et la
 * republication restent cohérentes. La vacation est créée aussitôt, marquée
 * remplacement (conservée à la republication, pas de « repos » fantôme).
 */
final class AffecterRondierAction
{
    public function __construct(
        private readonly CreateVacationAction $createVacation,
        private readonly RecouvrirVacationAction $recouvrir,
    ) {}

    public function execute(string $agentId, string $posteId, string $date, ?string $quart = null): Vacation
    {
        $agent = Agent::query()->with('grade')->findOrFail($agentId);
        if (! $agent->estRondier()) {
            throw ValidationException::withMessages([
                'agent_id' => $agent->libelle().' n’est pas rondier : changez son grade pour le planifier ici.',
            ]);
        }

        $aujourdhui = Carbon::now('Africa/Abidjan')->toDateString();
        if ($date < $aujourdhui) {
            throw ValidationException::withMessages([
                'date' => 'On ne peut placer un rondier que sur aujourd’hui ou un jour à venir.',
            ]);
        }

        $poste = Poste::query()->findOrFail($posteId);
        [$heureDebut, $heureFin, $quartModele] = self::horaires($poste, $quart);

        // Case « à recouvrir » sur ce créneau (titulaire absent) : la recouvrir.
        $trou = RecouvrirVacationAction::trouDuCreneau([
            'poste_id' => $poste->id,
            'agent_id' => $agent->id,
            'date_debut' => $date,
            'heure_debut' => $heureDebut,
            'heure_fin' => $heureFin,
        ]);
        if ($trou && ! $trou->remplacee) {
            return $this->recouvrir->execute($trou, (string) $agent->id);
        }

        return DB::transaction(function () use ($agent, $poste, $date, $heureDebut, $heureFin, $quartModele) {
            // Place libre par rapport à l’effectif NOMINAL du poste (et non au
            // modèle, que l’ajout du créneau rondier ferait grossir).
            Poste::query()->whereKey($poste->id)->lockForUpdate()->first();
            if (! self::placeLibre($poste, $date, $heureDebut, $heureFin)) {
                throw ValidationException::withMessages([
                    'poste_id' => self::messagePosteComplet($poste, $date, $heureDebut, $heureFin),
                ]);
            }

            $iso = PlanningSemaine::isoFromDate($date);
            $planning = PlanningSemaine::query()
                ->where('poste_id', $poste->id)
                ->where('annee_iso', $iso['annee_iso'])
                ->where('semaine_iso', $iso['semaine_iso'])
                ->lockForUpdate()
                ->first();

            if ($planning) {
                $jour = (int) Carbon::parse($date)->dayOfWeekIso;
                $existe = $planning->creneaux()
                    ->where('agent_id', $agent->id)
                    ->where('jour', $jour)
                    ->get()
                    ->contains(fn (PlanningSemaineCreneau $c) => $c->heureDebutHm() === $heureDebut);
                if (! $existe) {
                    PlanningSemaineCreneau::query()->create([
                        'planning_semaine_id' => $planning->id,
                        'agent_id' => $agent->id,
                        'jour' => $jour,
                        'heure_debut' => $heureDebut,
                        'heure_fin' => $heureFin,
                        'quart' => $quartModele,
                    ]);
                }
            }

            try {
                return $this->createVacation->execute([
                    'agent_id' => $agent->id,
                    'site_id' => $poste->site_id,
                    'poste_id' => $poste->id,
                    'date_debut' => $date,
                    // Un seul jour (la nuit finit le lendemain : normalisé à la création).
                    'date_fin' => $date,
                    'heure_debut' => $heureDebut,
                    'heure_fin' => $heureFin,
                    'reconduite' => false,
                    'heures_supplementaires' => false,
                    'remplacement' => true,
                ], wrapInTransaction: false);
            } catch (ValidationException $e) {
                // Poste complet : c’est le POSTE qui est occupé (par un titulaire),
                // pas le rondier — le dire clairement.
                if (isset($e->errors()['poste_id'])) {
                    throw ValidationException::withMessages([
                        'poste_id' => self::messagePosteComplet($poste, $date, $heureDebut, $heureFin),
                    ]);
                }
                throw $e;
            }
        });
    }

    /** Reste-t-il une place sur ce poste, ce jour, ce quart (effectif nominal) ? */
    public static function placeLibre(Poste $poste, string $date, string $heureDebut, string $heureFin): bool
    {
        $jour = Carbon::parse($date, 'Africa/Abidjan')->startOfDay();
        $tenues = Vacation::query()
            ->where('poste_id', $poste->id)
            ->whereNotIn('statut', [StatutVacation::Annulee->value, StatutVacation::ARecouvrir->value])
            ->whereDate('date_debut', '<=', $date)
            ->where(fn ($q) => $q->whereNull('date_fin')
                ->orWhereDate('date_fin', '>=', $jour->copy()->subDay()->toDateString()))
            ->get();

        foreach (PosteEffectifRules::quartsTouched($poste, $jour, $heureDebut, $heureFin) as $quart) {
            $nominal = $quart === '_simple'
                ? PosteEffectifRules::capaciteSimple($poste)
                : PosteEffectifRules::capaciteParQuart($poste);
            $tenu = $quart === '_simple'
                ? $tenues->filter(fn (Vacation $v) => PosteEffectifRules::vacationCoversPlanningDay($v, $jour))
                    ->pluck('agent_id')->unique()->count()
                : PosteEffectifRules::countOnQuart($tenues, $poste, $jour, $quart);
            if ($tenu >= $nominal) {
                return false;
            }
        }

        return true;
    }

    private static function messagePosteComplet(Poste $poste, string $date, string $heureDebut, string $heureFin): string
    {
        $poste->loadMissing('site:id,nom');
        $tenants = Vacation::query()
            ->with('agent:id,nom,prenom,matricule')
            ->where('poste_id', $poste->id)
            ->whereDate('date_debut', $date)
            ->whereNotIn('statut', ['annulee', 'a_recouvrir'])
            ->get()
            ->filter(fn (Vacation $v) => substr((string) $v->heure_debut, 0, 5) === $heureDebut)
            ->map(fn (Vacation $v) => $v->agent?->libelle())
            ->filter()
            ->unique()
            ->values();

        return sprintf(
            'Pas de place sur le poste « %s »%s de %s à %s : il est déjà tenu%s. Choisissez un autre quart, un autre poste ou un « Trou à combler ».',
            $poste->nom,
            $poste->site ? ' ('.$poste->site->nom.')' : '',
            $heureDebut,
            $heureFin,
            $tenants->isNotEmpty() ? ' par '.$tenants->implode(', ') : '',
        );
    }

    /**
     * @return array{0: string, 1: string, 2: string} heure début, heure fin, quart du modèle
     */
    public static function horaires(Poste $poste, ?string $quart): array
    {
        $windows = PosteEffectifRules::quartWindows($poste);
        if ($windows !== null) {
            $q = $quart === 'nuit' ? 'nuit' : 'jour';

            return [$windows[$q]['heure_debut'], $windows[$q]['heure_fin'], $q];
        }

        $debut = ShiftInterval::hm((string) ($poste->heure_debut ?? '07:00'));
        $fin = ShiftInterval::hm((string) ($poste->heure_fin ?? '19:00'));

        return [$debut, $fin, $quart === 'nuit' ? 'nuit' : 'jour'];
    }
}
