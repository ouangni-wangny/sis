<?php

use App\Application\Operation\ProlongerPlanningPostesAction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

/**
 * Reprise unique au déploiement de la reconduction : relance les plannings arrêtés
 * depuis moins d’un mois, équivalent de `vacations:prolonger-planning --reprendre`.
 * Non bloquante et plafonnée pour tenir dans la limite de temps du déploiement ;
 * si elle n’a pas tout traité, la commande termine le travail (elle est rejouable).
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        try {
            $resultat = app(ProlongerPlanningPostesAction::class)->execute(reprendre: true, dureeMaxSecondes: 600);
            Log::info('Reprise des plannings', collect($resultat)->except('errors')->all());
            echo sprintf(
                "Reprise des plannings : %d poste(s), %d créneau(x) créé(s), %d sauté(s), %d poste(s) restant(s).\n",
                $resultat['postes'],
                $resultat['created'],
                $resultat['skipped'],
                $resultat['postes_restants'],
            );
        } catch (Throwable $e) {
            Log::error('Reprise des plannings échouée', ['message' => $e->getMessage()]);
            echo 'Reprise des plannings échouée : '.$e->getMessage()."\n";
        }
    }

    public function down(): void
    {
        // Les créneaux créés restent : ils se suppriment par le planning.
    }
};
