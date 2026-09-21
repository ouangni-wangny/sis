<?php

namespace App\Application\Agent;

use App\Domain\Shared\Enums\StatutAgent;
use App\Domain\Shared\Enums\StatutVacation;
use App\Domain\Shared\Enums\TypeAgent;
use App\Models\Agent;
use App\Models\Grade;
use App\Models\Vacation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class UpdateAgentAction
{
    /** Statuts qui rendent l'agent indisponible pour ses vacations en cours/à venir. */
    private const UNAVAILABLE_STATUTS = [
        StatutAgent::Conge,
        StatutAgent::Malade,
        StatutAgent::Suspendu,
        StatutAgent::Archive,
    ];

    public function execute(Agent $agent, array $data): Agent
    {
        return DB::transaction(function () use ($agent, $data) {
            $previousStatut = $agent->statut;
            $pin = $data['pin'] ?? null;
            $hasEmail = array_key_exists('email', $data);
            $email = $hasEmail ? $data['email'] : null;
            unset($data['contrat'], $data['pin'], $data['email'], $data['matricule']);

            // Le type (agent posté / controleur) doit toujours refléter le
            // grade actuel — sinon un changement de grade à l'édition ne
            // se répercute jamais (l'agent reste "controleur" indéfiniment).
            if (isset($data['grade_id'])) {
                $grade = Grade::query()->findOrFail($data['grade_id']);
                $wasControleur = $agent->type === TypeAgent::Controleur;
                $data['type'] = $grade->type_agent->value;

                // Rétrograder un controleur lui retire son périmètre : il ne
                // patrouille plus rien, son statut ne peut donc plus rester
                // "en activité" — il redevient disponible pour une
                // nouvelle affectation.
                if ($wasControleur && $grade->type_agent !== TypeAgent::Controleur) {
                    $data['statut'] = 'disponible';
                }
            }

            if ($data !== []) {
                $agent->update($data);
            }

            // Un agent qui devient indisponible (congé, maladie, suspension,
            // archivage) ne peut plus honorer ses vacations planifiées ou en
            // cours : elles doivent être recouvertes par quelqu'un d'autre,
            // pas rester silencieusement affichées comme "planifiée".
            if (
                $previousStatut !== $agent->statut
                && in_array($agent->statut, self::UNAVAILABLE_STATUTS, true)
            ) {
                $today = now()->toDateString();
                Vacation::query()
                    ->where('agent_id', $agent->id)
                    ->whereIn('statut', [
                        StatutVacation::Planifiee->value,
                        StatutVacation::EnCours->value,
                    ])
                    ->where(function ($q) use ($today) {
                        $q->whereNull('date_fin')->orWhereDate('date_fin', '>=', $today);
                    })
                    ->update(['statut' => StatutVacation::ARecouvrir->value]);
            }

            $agent->loadMissing('user');
            $user = $agent->user;

            if ($user) {
                $userUpdates = [];

                if (isset($data['type'])) {
                    $user->syncRoles([$data['type']]);
                }

                if (filled($pin)) {
                    $userUpdates['pin_hash'] = Hash::make((string) $pin);
                    $agent->setAttribute('plain_pin', (string) $pin);
                }

                if ($hasEmail && filled($email)) {
                    $userUpdates['email'] = (string) $email;
                }

                if ($userUpdates !== []) {
                    if (isset($data['nom']) || isset($data['prenom'])) {
                        $userUpdates['nom'] = $agent->nom;
                        $userUpdates['prenom'] = $agent->prenom;
                        $userUpdates['name'] = trim($agent->prenom.' '.$agent->nom);
                    }
                    $user->update($userUpdates);
                }
            }

            $fresh = $agent->fresh()->load(['grade', 'villeRef', 'media', 'user', 'perimetres.zone', 'contratActif', 'posteSiege.site']);

            if (isset($agent->plain_pin)) {
                $fresh->setAttribute('plain_pin', $agent->plain_pin);
            }

            return $fresh;
        });
    }
}
