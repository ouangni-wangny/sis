<?php

namespace App\Console\Commands;

use App\Application\Operation\GenererVacationsPoolSiegeAction;
use Illuminate\Console\Command;

class GenererVacationsPoolSiegeCommand extends Command
{
    protected $signature = 'vacations:generer-pool-siege
                            {--date= : Date cible (Y-m-d), défaut aujourd’hui}';

    protected $description = 'Pointe automatiquement les agents du pool siège via une vacation du jour sur leur poste siège';

    public function handle(GenererVacationsPoolSiegeAction $action): int
    {
        $result = $action->execute($this->option('date'));

        $this->info("Pool siège : {$result['created']} vacation(s) créée(s), {$result['skipped']} ignorée(s).");

        foreach ($result['errors'] as $error) {
            $this->line("  - {$error}");
        }

        return self::SUCCESS;
    }
}
