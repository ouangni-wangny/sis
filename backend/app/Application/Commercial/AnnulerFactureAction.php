<?php

namespace App\Application\Commercial;

use App\Application\Tresorerie\PosterMouvementTresorerieAction;
use App\Domain\Shared\Enums\SourceMouvementTresorerie;
use App\Domain\Shared\Enums\StatutFacture;
use App\Models\Facture;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class AnnulerFactureAction
{
    public function __construct(
        private readonly PosterMouvementTresorerieAction $poster,
    ) {}

    public function execute(Facture $facture): Facture
    {
        return DB::transaction(function () use ($facture) {
            $facture = $facture->fresh(['paiements']) ?? $facture;

            if ($facture->statut === StatutFacture::Annule) {
                return $facture;
            }

            if ($facture->statut === StatutFacture::Valide) {
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
            }

            $facture->update(['statut' => StatutFacture::Annule]);

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
