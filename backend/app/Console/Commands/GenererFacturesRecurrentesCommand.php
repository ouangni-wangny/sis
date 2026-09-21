<?php

namespace App\Console\Commands;

use App\Application\Commercial\GenererFacturesRecurrentesAction;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class GenererFacturesRecurrentesCommand extends Command
{
    protected $signature = 'factures:generer-recurrentes
                            {--date= : Date de référence (Y-m-d), défaut = aujourd’hui Abidjan}';

    protected $description = 'Génère les factures d’abonnements arrivées à échéance';

    public function handle(GenererFacturesRecurrentesAction $action): int
    {
        $asOf = $this->option('date')
            ? Carbon::parse((string) $this->option('date'), 'Africa/Abidjan')->startOfDay()
            : null;

        $stats = $action->execute($asOf);

        $this->info(sprintf(
            'Terminé — générées: %d, ignorées: %d, erreurs: %d',
            $stats['generated'],
            $stats['skipped'],
            $stats['errors'],
        ));

        return $stats['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
