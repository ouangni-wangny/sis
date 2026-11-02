<?php

namespace App\Application\Paie;

use App\Domain\Shared\Enums\StatutAvanceSalaire;
use App\Models\AvanceSalaire;
use App\Models\BulletinPaie;
use Illuminate\Validation\ValidationException;

final class DeduireAvancesSalaireAction
{
    /**
     * À appeler dans la transaction du règlement : retient les avances du mois sur
     * le bulletin et renvoie ce qu’il reste à verser.
     *
     * @return array{avances: float, reste: float}
     */
    public function execute(BulletinPaie $bulletin, float $net): array
    {
        $periode = $bulletin->periodePaie;

        $avances = AvanceSalaire::query()
            ->where('agent_id', $bulletin->agent_id)
            ->where('mois', $periode?->mois)
            ->where('annee', $periode?->annee)
            ->where('statut', StatutAvanceSalaire::EnCours->value)
            ->lockForUpdate()
            ->get();

        $total = round($avances->sum(fn (AvanceSalaire $a) => (float) $a->montant), 2);

        if ($total > $net) {
            throw ValidationException::withMessages([
                'avances' => sprintf(
                    'Les avances de %s (%s FCFA) dépassent le salaire net (%s FCFA).',
                    trim(($bulletin->details['agent'] ?? 'cet agent')),
                    number_format($total, 0, ',', ' '),
                    number_format($net, 0, ',', ' '),
                ),
            ]);
        }

        foreach ($avances as $avance) {
            $avance->update([
                'statut' => StatutAvanceSalaire::Deduite,
                'bulletin_paie_id' => $bulletin->id,
            ]);
        }

        return ['avances' => $total, 'reste' => round($net - $total, 2)];
    }
}
