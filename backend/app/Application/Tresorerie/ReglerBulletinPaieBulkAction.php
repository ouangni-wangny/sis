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

final class ReglerBulletinPaieBulkAction
{
    public function __construct(
        private readonly PosterMouvementTresorerieAction $poster,
    ) {}

    /**
     * @param  array{
     *   bulletin_ids: list<string>,
     *   mode: string,
     *   compte_tresorerie_id: string,
     *   reference?: string|null,
     *   paye_le?: string|null,
     * }  $data
     * @return array{updated: int}
     */
    public function execute(array $data): array
    {
        $ids = array_values(array_unique($data['bulletin_ids']));
        if ($ids === []) {
            throw ValidationException::withMessages([
                'bulletin_ids' => 'Aucun bulletin à régler.',
            ]);
        }

        $bulletins = BulletinPaie::query()
            ->with('periodePaie')
            ->whereIn('id', $ids)
            ->get();

        if ($bulletins->count() !== count($ids)) {
            throw ValidationException::withMessages([
                'bulletin_ids' => 'Un ou plusieurs bulletins sont introuvables.',
            ]);
        }

        $moduleEnabled = PosterMouvementTresorerieAction::isModuleEnabled();
        $payeLe = $data['paye_le'] ?? now()->toDateTimeString();
        $date = is_string($payeLe) ? substr($payeLe, 0, 10) : now()->toDateString();
        $baseRef = trim((string) ($data['reference'] ?? ''));

        return DB::transaction(function () use ($bulletins, $data, $moduleEnabled, $payeLe, $date, $baseRef) {
            $updated = 0;

            foreach ($bulletins as $bulletin) {
                if ($bulletin->statut === StatutBulletinPaie::Paye) {
                    throw ValidationException::withMessages([
                        'bulletin_ids' => 'Un bulletin sélectionné est déjà payé.',
                    ]);
                }

                $periodeValidee = $bulletin->periodePaie?->statut === StatutPeriodePaie::Validee
                    || $bulletin->periodePaie?->statut === StatutPeriodePaie::Cloturee;

                if ($bulletin->statut === StatutBulletinPaie::Brouillon && ! $periodeValidee) {
                    throw ValidationException::withMessages([
                        'bulletin_ids' => 'Validez la période avant le règlement groupé.',
                    ]);
                }

                $details = is_array($bulletin->details) ? $bulletin->details : [];
                $net = round((float) ($details['salaire_percu'] ?? $bulletin->salaire_net ?? 0), 2);
                $renseigne = array_key_exists('salaire_percu', $details)
                    || array_key_exists('salaire_renseigne_le', $details);

                if (! $renseigne || $net <= 0) {
                    throw ValidationException::withMessages([
                        'bulletin_ids' => 'La RH doit d’abord saisir le salaire perçu pour tous les bulletins sélectionnés.',
                    ]);
                }

                $reference = $baseRef !== ''
                    ? $baseRef
                    : 'PAIE-'.now()->format('Ymd').'-'.Str::upper(Str::random(5));

                $details['salaire_calcule_net'] = $details['salaire_calcule_net'] ?? $bulletin->salaire_net;
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

                if ($moduleEnabled) {
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

                $updated++;
            }

            return ['updated' => $updated];
        });
    }
}
