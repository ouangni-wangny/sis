<?php

namespace App\Application\Operation;

use App\Domain\Shared\Enums\StatutPlanningSemaine;
use App\Models\PlanningSemaine;
use App\Models\PlanningSemaineCreneau;
use App\Models\Poste;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpsertPlanningSemaineAction
{
    /**
     * @param  array{
     *   poste_id: string,
     *   annee_iso: int,
     *   semaine_iso: int,
     *   creneaux: list<array{agent_id: string, jour: int, heure_debut: string, heure_fin: string, quart?: string}>
     * }  $data
     */
    public function execute(array $data): PlanningSemaine
    {
        return DB::transaction(function () use ($data) {
            $poste = Poste::query()->findOrFail($data['poste_id']);
            $annee = (int) $data['annee_iso'];
            $semaine = (int) $data['semaine_iso'];

            $semainesDansAnnee = (int) Carbon::create($annee, 12, 28)->isoWeek();
            if ($semaine < 1 || $semaine > $semainesDansAnnee) {
                throw ValidationException::withMessages([
                    'semaine_iso' => "Numéro de semaine invalide : l’année {$annee} compte {$semainesDansAnnee} semaines.",
                ]);
            }

            $planning = PlanningSemaine::query()->firstOrNew([
                'poste_id' => $poste->id,
                'annee_iso' => $annee,
                'semaine_iso' => $semaine,
            ]);

            if (! $planning->exists) {
                $planning->statut = StatutPlanningSemaine::Brouillon;
            }

            $planning->save();
            $planning->load('creneaux.agent.grade');

            // Jours déjà passés : ne les reconstituer que pour les agents
            // absents du payload (évite d’effacer lun/mar par oubli UI).
            // Si l’agent est présent dans le payload, son rythme (y compris repos
            // sur un jour passé) est la source de vérité — ne pas réinjecter
            // d’anciens créneaux.
            $aujourdHui = now('Africa/Abidjan')->toDateString();
            $payloadKeys = [];
            $agentsPayload = [];
            foreach ($data['creneaux'] as $row) {
                $agentId = (string) $row['agent_id'];
                $agentsPayload[$agentId] = true;
                $payloadKeys[$this->creneauKey(
                    $agentId,
                    (int) $row['jour'],
                    (string) $row['heure_debut'],
                    (string) ($row['quart'] ?? 'jour'),
                )] = true;
            }

            $aConserver = [];
            foreach ($planning->creneaux as $existant) {
                $date = $existant->datePourSemaine($planning);
                // Les créneaux des rondiers se gèrent depuis le planning des
                // rondiers : conservés tant qu’ils ne sont pas dans le payload.
                $rondier = (bool) $existant->agent?->grade?->est_rondier;
                if ($date >= $aujourdHui && ! $rondier) {
                    continue;
                }
                if (isset($agentsPayload[$existant->agent_id])) {
                    continue;
                }
                $key = $this->creneauKey(
                    $existant->agent_id,
                    (int) $existant->jour,
                    $existant->heureDebutHm(),
                    (string) $existant->quart,
                );
                if (! isset($payloadKeys[$key])) {
                    $aConserver[] = [
                        'agent_id' => $existant->agent_id,
                        'jour' => (int) $existant->jour,
                        'heure_debut' => $existant->heureDebutHm(),
                        'heure_fin' => $existant->heureFinHm(),
                        'quart' => $existant->quart,
                    ];
                }
            }

            $planning->creneaux()->delete();

            $tous = [...$data['creneaux'], ...$aConserver];
            foreach ($tous as $row) {
                $jour = (int) $row['jour'];
                if ($jour < 1 || $jour > 7) {
                    throw ValidationException::withMessages([
                        'creneaux' => 'Le jour doit être entre 1 (lundi) et 7 (dimanche).',
                    ]);
                }

                PlanningSemaineCreneau::query()->create([
                    'planning_semaine_id' => $planning->id,
                    'agent_id' => $row['agent_id'],
                    'jour' => $jour,
                    'heure_debut' => $row['heure_debut'],
                    'heure_fin' => $row['heure_fin'],
                    'quart' => $row['quart'] ?? 'jour',
                ]);
            }

            // Republier plus tard : repasser en brouillon si on édite une semaine déjà publiée.
            if ($planning->statut === StatutPlanningSemaine::Publiee) {
                $planning->update(['statut' => StatutPlanningSemaine::Brouillon]);
            }

            return $planning->fresh(['creneaux.agent.grade', 'poste.site']);
        });
    }

    private function creneauKey(string $agentId, int $jour, string $heureDebut, string $quart): string
    {
        return $agentId.'|'.$jour.'|'.substr($heureDebut, 0, 5).'|'.$quart;
    }
}
