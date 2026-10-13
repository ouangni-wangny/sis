<?php

namespace App\Application\Commercial;

use App\Application\Tresorerie\PosterMouvementTresorerieAction;
use App\Domain\Shared\Enums\SourceMouvementTresorerie;
use App\Domain\Shared\Enums\StatutFacture;
use App\Models\Facture;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class AnnulerFactureAction
{
    public function __construct(
        private readonly PosterMouvementTresorerieAction $poster,
    ) {}

    /**
     * Annule une facture déjà validée (statut → annulee) et contrepassera les encaissements.
     * Pour rejeter une proforma, utiliser le statut `rejetee` via updateStatut.
     */
    public function execute(Facture $facture): Facture
    {
        return DB::transaction(function () use ($facture) {
            $facture = $facture->fresh(['paiements']) ?? $facture;

            if ($facture->statut === StatutFacture::Annulee) {
                return $facture;
            }

            if ($facture->statut !== StatutFacture::Valide) {
                throw ValidationException::withMessages([
                    'statut' => 'Seule une facture validée peut être annulée. Pour une proforma, utilisez le rejet.',
                ]);
            }

            $this->assertPeutAnnulerFactureValidee(Auth::user());

            foreach ($facture->paiements as $paiement) {
                if (PosterMouvementTresorerieAction::isModuleEnabled()) {
                    $this->poster->reverseForSource(
                        SourceMouvementTresorerie::FacturePaiement,
                        $paiement->id,
                        'Annulation facture',
                    );
                }
                $paiement->delete();
            }

            $facture->update(['statut' => StatutFacture::Annulee]);

            return $facture->fresh(['client', 'abonnement', 'lignes', 'media', 'paiements']) ?? $facture;
        });
    }

    private function assertPeutAnnulerFactureValidee(?User $user): void
    {
        if ($user && ($user->hasRole('super-admin') || $user->hasRole('developpeur'))) {
            return;
        }

        throw new HttpException(
            403,
            'Seuls les administrateurs peuvent annuler une facture validée.',
        );
    }
}
