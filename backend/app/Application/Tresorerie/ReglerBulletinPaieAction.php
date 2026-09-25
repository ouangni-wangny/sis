<?php

namespace App\Application\Tresorerie;

use App\Domain\Shared\Enums\DirectionMouvementTresorerie;
use App\Domain\Shared\Enums\SourceMouvementTresorerie;
use App\Domain\Shared\Enums\StatutBulletinPaie;
use App\Domain\Shared\Enums\StatutPeriodePaie;
use App\Models\BulletinPaie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ReglerBulletinPaieAction
{
    public function __construct(
        private readonly PosterMouvementTresorerieAction $poster,
    ) {}

    /**
     * @param  array{
     *   salaire_net: float|int|string,
     *   mode: string,
     *   compte_tresorerie_id: string,
     *   reference?: string|null,
     *   paye_le?: string|null,
     * }  $data
     */
    public function execute(BulletinPaie $bulletin, array $data): BulletinPaie
    {
        $bulletin->loadMissing('periodePaie');

        if ($bulletin->statut === StatutBulletinPaie::Paye) {
            throw ValidationException::withMessages([
                'statut' => 'Ce bulletin est déjà marqué comme payé.',
            ]);
        }

        $periodeValidee = $bulletin->periodePaie?->statut === StatutPeriodePaie::Validee
            || $bulletin->periodePaie?->statut === StatutPeriodePaie::Cloturee;

        if ($bulletin->statut === StatutBulletinPaie::Brouillon && ! $periodeValidee) {
            throw ValidationException::withMessages([
                'statut' => 'Validez la période avant le règlement, ou validez le bulletin.',
            ]);
        }

        $net = round((float) $data['salaire_net'], 2);
        if ($net <= 0) {
            throw ValidationException::withMessages([
                'salaire_net' => 'Le salaire perçu doit être supérieur à 0.',
            ]);
        }

        return DB::transaction(function () use ($bulletin, $data, $net) {
            $payeLe = $data['paye_le'] ?? now()->toDateTimeString();
            $reference = trim((string) ($data['reference'] ?? ''));
            if ($reference === '') {
                $reference = 'PAIE-'.now()->format('Ymd').'-'.Str::upper(Str::random(5));
            }

            $details = is_array($bulletin->details) ? $bulletin->details : [];
            $details['salaire_calcule_net'] = $bulletin->salaire_net;
            $details['salaire_percu'] = $net;

            $bulletin->update([
                'salaire_net' => $net,
                'statut' => StatutBulletinPaie::Paye,
                'paye_le' => $payeLe,
                'mode_paiement' => $data['mode'],
                'compte_tresorerie_id' => $data['compte_tresorerie_id'],
                'reference_paiement' => $reference,
                'details' => $details,
            ]);

            if (PosterMouvementTresorerieAction::isModuleEnabled()) {
                $date = is_string($payeLe)
                    ? substr($payeLe, 0, 10)
                    : now()->toDateString();

                $this->poster->execute([
                    'compte_tresorerie_id' => $data['compte_tresorerie_id'],
                    'direction' => DirectionMouvementTresorerie::Sortie,
                    'montant' => $net,
                    'date_mouvement' => $date,
                    'mode' => $data['mode'],
                    'source_type' => SourceMouvementTresorerie::BulletinPaie,
                    'source_id' => $bulletin->id,
                    'reference' => $reference,
                    'notes' => 'Règlement paie bulletin',
                ]);
            }

            return $bulletin->fresh(['agent', 'contrat', 'periodePaie', 'compteTresorerie', 'media']);
        });
    }
}
