<?php

namespace App\Jobs;

use App\Application\Rh\CreateSisNotificationAction;
use App\Application\Rh\GetContratsAlertsAction;
use App\Models\Absence;
use App\Models\Agent;
use App\Models\Contrat;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class AlerteExpirationDocumentsJob implements ShouldQueue
{
    use Queueable;

    public function handle(CreateSisNotificationAction $notifications, GetContratsAlertsAction $alerts): void
    {
        $soon = now()->addDays(30)->toDateString();

        $agentsPermis = Agent::query()
            ->whereNotNull('date_expiration_permis')
            ->whereDate('date_expiration_permis', '<=', $soon)
            ->get(['id', 'matricule', 'nom', 'prenom', 'date_expiration_permis']);

        $contrats = Contrat::query()
            ->where('statut', 'actif')
            ->whereNotNull('date_fin')
            ->whereDate('date_fin', '<=', $soon)
            ->count();

        $absencesEnAttente = Absence::query()->where('statut', 'en_attente')->count();

        Log::info('Alertes expiration documents', [
            'agents_permis' => $agentsPermis->count(),
            'contrats' => $contrats,
            'absences_en_attente' => $absencesEnAttente,
        ]);

        if ($agentsPermis->isNotEmpty() || $contrats > 0) {
            $notifications->notifyRhUsers(
                'documents_expiration',
                'Documents / contrats à échéance',
                "{$agentsPermis->count()} permis et {$contrats} contrat(s) arrivent à échéance sous 30 jours.",
            );
        }

        if ($absencesEnAttente > 0) {
            $notifications->notifyRhUsers(
                'absences_en_attente',
                'Absences en attente',
                "{$absencesEnAttente} absence(s) attendent une validation.",
            );
        }

        $contratsAlertes = $alerts->execute();
        if (count($contratsAlertes) > 0) {
            $notifications->notifyRhUsers(
                'contrats_surveillance',
                'Contrats à surveiller',
                count($contratsAlertes).' contrat(s) nécessitent votre attention (fin ou essai).',
            );
        }
    }
}
