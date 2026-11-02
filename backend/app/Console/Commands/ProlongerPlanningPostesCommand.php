<?php

namespace App\Console\Commands;

use App\Application\Operation\ProlongerPlanningPostesAction;
use Illuminate\Console\Command;

class ProlongerPlanningPostesCommand extends Command
{
    protected $signature = 'vacations:prolonger-planning
                            {--reprendre : Relance aussi les plannings arrêtés depuis moins d’un mois}';

    protected $description = 'Reconduit le rythme des postes pour garder le planning rempli à l’avance';

    public function handle(ProlongerPlanningPostesAction $action): int
    {
        $result = $action->execute(reprendre: (bool) $this->option('reprendre'));

        $this->info("Planning : {$result['postes']} poste(s), {$result['created']} créneau(x) créé(s), {$result['skipped']} sauté(s).");

        foreach ($result['errors'] as $error) {
            $this->line("  - {$error}");
        }

        return self::SUCCESS;
    }
}
