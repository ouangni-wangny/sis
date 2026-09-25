<?php

namespace App\Console\Commands;

use App\Domain\Shared\Enums\SourceMouvementTresorerie;
use App\Models\CompteTresorerie;
use App\Models\MouvementTresorerie;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Corrige le double impact solde causé par l’ancienne reverseForSource
 * (soft-delete de l’original + mouvement inverse).
 *
 * Remède : restaurer les mouvements d’origine soft-deleted lorsqu’un
 * ajustement d’annulation correspondant existe déjà.
 */
class FixDoubleReverseTresorerieCommand extends Command
{
    protected $signature = 'tresorerie:fix-double-reverse
                            {--dry-run : Afficher sans modifier}
                            {--force : Exécuter sans confirmation}';

    protected $description = 'Restaure les mouvements soft-deleted qui avaient déjà un inverse (solde corrigé deux fois)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $candidates = MouvementTresorerie::onlyTrashed()
            ->whereIn('source_type', [
                SourceMouvementTresorerie::Depense->value,
                SourceMouvementTresorerie::FacturePaiement->value,
            ])
            ->orderBy('deleted_at')
            ->get();

        if ($candidates->isEmpty()) {
            $this->info('Aucun mouvement soft-deleted à corriger.');
            $this->printSoldes();

            return self::SUCCESS;
        }

        $toRestore = [];

        foreach ($candidates as $original) {
            $inverseDir = $original->direction->inverse()->value;

            $inverse = MouvementTresorerie::query()
                ->where('source_type', SourceMouvementTresorerie::Ajustement->value)
                ->where('compte_tresorerie_id', $original->compte_tresorerie_id)
                ->where('direction', $inverseDir)
                ->where('montant', $original->montant)
                ->where(function ($q) use ($original) {
                    $q->where('notes', 'like', 'Annulation%')
                        ->orWhere('notes', 'like', 'Suppression encaissement%')
                        ->orWhere('notes', 'like', 'Correction encaissement%');
                    if ($original->reference) {
                        $q->orWhere('reference', $original->reference);
                    }
                })
                ->where('created_at', '>=', $original->created_at)
                ->orderBy('created_at')
                ->first();

            if (! $inverse) {
                $this->warn(sprintf(
                    'Ignoré %s #%s (soft-deleted, pas d’inverse trouvé) — montant %s',
                    $original->source_type->value,
                    substr($original->id, 0, 8),
                    $original->montant,
                ));

                continue;
            }

            $toRestore[] = [
                'original' => $original,
                'inverse' => $inverse,
            ];
        }

        if ($toRestore === []) {
            $this->info('Rien à restaurer (aucun couple original+inverse).');
            $this->printSoldes();

            return self::SUCCESS;
        }

        $this->table(
            ['Source', 'Montant', 'Compte', 'Original (trashed)', 'Inverse'],
            array_map(function (array $row) {
                /** @var MouvementTresorerie $o */
                $o = $row['original'];
                /** @var MouvementTresorerie $i */
                $i = $row['inverse'];

                return [
                    $o->source_type->value,
                    $o->montant,
                    substr($o->compte_tresorerie_id, 0, 8),
                    substr($o->id, 0, 8),
                    substr($i->id, 0, 8),
                ];
            }, $toRestore),
        );

        $this->info(sprintf('%d mouvement(s) à restaurer.', count($toRestore)));

        if ($dryRun) {
            $this->comment('Dry-run : aucune modification.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Restaurer ces mouvements d’origine ?', true)) {
            $this->warn('Annulé.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($toRestore) {
            foreach ($toRestore as $row) {
                $row['original']->restore();
            }
        });

        $this->info(sprintf('%d mouvement(s) restauré(s).', count($toRestore)));
        $this->printSoldes();

        return self::SUCCESS;
    }

    private function printSoldes(): void
    {
        $this->newLine();
        $this->info('Soldes courants :');
        CompteTresorerie::query()->orderBy('libelle')->get()->each(function (CompteTresorerie $c) {
            $this->line(sprintf('  • %s : %s', $c->libelle, number_format($c->soldeCourant(), 0, ',', ' ')));
        });
    }
}
