<?php

namespace App\Application\Tresorerie;

use App\Domain\Shared\Enums\DirectionMouvementTresorerie;
use App\Domain\Shared\Enums\SourceMouvementTresorerie;
use App\Models\CompteTresorerie;
use App\Models\MouvementTresorerie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Transfert interne entre deux comptes de trésorerie : une sortie sur le compte
 * source et une entrée sur le compte destination, liées par le même source_id.
 */
final class CreateTransfertTresorerieAction
{
    public function __construct(
        private readonly PosterMouvementTresorerieAction $poster,
    ) {}

    /**
     * @param  array{
     *   compte_source_id: string,
     *   compte_destination_id: string,
     *   montant: float|string,
     *   date_mouvement: string,
     *   mode?: string,
     *   reference?: string|null,
     *   notes?: string|null,
     * }  $data
     * @return array{sortie: MouvementTresorerie, entree: MouvementTresorerie}
     */
    public function execute(array $data): array
    {
        if ($data['compte_source_id'] === $data['compte_destination_id']) {
            throw ValidationException::withMessages([
                'compte_destination_id' => 'Le compte destination doit être différent du compte source.',
            ]);
        }

        return DB::transaction(function () use ($data) {
            // Verrouille le compte source pour éviter deux transferts concurrents sur le même solde.
            $source = CompteTresorerie::query()->lockForUpdate()->findOrFail($data['compte_source_id']);
            $destination = CompteTresorerie::query()->findOrFail($data['compte_destination_id']);

            $montant = round((float) $data['montant'], 2);
            $solde = $source->soldeCourant();
            if ($montant > $solde) {
                throw ValidationException::withMessages([
                    'montant' => 'Solde insuffisant sur « '.$source->libelle.' » (disponible : '
                        .number_format($solde, 0, ',', ' ').' FCFA).',
                ]);
            }

            $transfertId = (string) Str::uuid7();
            $notes = $data['notes'] ?? null;
            $common = [
                'montant' => $montant,
                'date_mouvement' => $data['date_mouvement'],
                'mode' => $data['mode'] ?? 'virement',
                'source_type' => SourceMouvementTresorerie::Transfert,
                'source_id' => $transfertId,
                'reference' => $data['reference'] ?? null,
            ];

            $sortie = $this->poster->execute([
                ...$common,
                'compte_tresorerie_id' => $source->id,
                'direction' => DirectionMouvementTresorerie::Sortie,
                'notes' => $notes ?? 'Transfert vers '.$destination->libelle,
            ]);

            $entree = $this->poster->execute([
                ...$common,
                'compte_tresorerie_id' => $destination->id,
                'direction' => DirectionMouvementTresorerie::Entree,
                'notes' => $notes ?? 'Transfert depuis '.$source->libelle,
            ]);

            return ['sortie' => $sortie, 'entree' => $entree];
        });
    }
}
