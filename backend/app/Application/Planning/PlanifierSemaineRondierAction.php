<?php

namespace App\Application\Planning;

use App\Domain\Shared\Enums\StatutVacation;
use App\Domain\Shared\Exceptions\VacationConflitException;
use App\Domain\Shared\Support\ShiftInterval;
use App\Models\Agent;
use App\Models\Poste;
use App\Models\Vacation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Planning hebdomadaire d’un rondier en une fois : pour chaque jour envoyé
 * (aujourd’hui ou à venir), un poste ou « libre ».
 *
 * - jour identique à l’existant : conservé ;
 * - jour modifié : l’affectation précédente est retirée, la nouvelle créée ;
 * - jour « libre » : les affectations prévues du jour sont retirées.
 *
 * Tout ou rien : au premier refus (conflit, repos, absence…), rien n’est
 * enregistré et le jour fautif est nommé.
 */
final class PlanifierSemaineRondierAction
{
    public function __construct(
        private readonly AffecterRondierAction $affecter,
        private readonly RetirerRondierAction $retirer,
    ) {}

    /**
     * @param  list<array{date: string, poste_id?: string|null, quart?: string|null}>  $jours
     * @return array{creees: int, retirees: int, conservees: int}
     */
    public function execute(string $agentId, array $jours): array
    {
        $agent = Agent::query()->with('grade')->findOrFail($agentId);
        if (! $agent->estRondier()) {
            throw ValidationException::withMessages([
                'agent_id' => $agent->libelle().' n’est pas rondier : changez son grade pour le planifier ici.',
            ]);
        }
        $aujourdhui = Carbon::now('Africa/Abidjan')->toDateString();

        return DB::transaction(function () use ($agent, $jours, $aujourdhui) {
            $bilan = ['creees' => 0, 'retirees' => 0, 'conservees' => 0];

            foreach ($jours as $jour) {
                $date = $jour['date'];
                if ($date < $aujourdhui) {
                    continue;
                }

                try {
                    $existantes = Vacation::query()
                        ->where('agent_id', $agent->id)
                        ->where('statut', '!=', StatutVacation::Annulee->value)
                        ->whereDate('date_debut', $date)
                        ->get();

                    $garder = null;
                    $posteId = $jour['poste_id'] ?? null;
                    if ($posteId) {
                        [$heureDebut] = AffecterRondierAction::horaires(
                            Poste::query()->findOrFail($posteId),
                            $jour['quart'] ?? null,
                        );
                        $garder = $existantes->first(fn (Vacation $v) => (string) $v->poste_id === (string) $posteId
                            && ShiftInterval::hm((string) $v->heure_debut) === $heureDebut);
                    }

                    foreach ($existantes as $v) {
                        if ($garder && $v->is($garder)) {
                            continue;
                        }
                        if ($v->statut === StatutVacation::Planifiee) {
                            $this->retirer->execute($v);
                            $bilan['retirees']++;
                        }
                    }

                    if ($garder) {
                        $bilan['conservees']++;
                    } elseif ($posteId) {
                        $this->affecter->execute((string) $agent->id, $posteId, $date, $jour['quart'] ?? null);
                        $bilan['creees']++;
                    }
                } catch (ValidationException $e) {
                    throw ValidationException::withMessages([
                        'jours' => self::jour($date).' : '.collect($e->errors())->flatten()->first(),
                    ]);
                } catch (VacationConflitException $e) {
                    throw ValidationException::withMessages([
                        'jours' => self::jour($date).' : '.$e->getMessage(),
                    ]);
                }
            }

            return $bilan;
        });
    }

    private static function jour(string $date): string
    {
        return ucfirst(Carbon::parse($date)->locale('fr')->isoFormat('dddd D/MM'));
    }
}
