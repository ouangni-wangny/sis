<?php

namespace App\Application\Paie;

use App\Domain\Shared\Enums\StatutBulletinPaie;
use App\Models\BulletinPaie;
use Illuminate\Validation\ValidationException;

final class RenseignerSalairePercuAction
{
    /**
     * @param  array{salaire_net: float|int|string}  $data
     */
    public function execute(BulletinPaie $bulletin, array $data): BulletinPaie
    {
        if ($bulletin->statut === StatutBulletinPaie::Paye) {
            throw ValidationException::withMessages([
                'statut' => 'Ce bulletin est déjà payé : le salaire perçu ne peut plus être modifié.',
            ]);
        }

        $net = round((float) $data['salaire_net'], 2);
        if ($net <= 0) {
            throw ValidationException::withMessages([
                'salaire_net' => 'Le salaire perçu doit être supérieur à 0.',
            ]);
        }

        $details = is_array($bulletin->details) ? $bulletin->details : [];
        if (! array_key_exists('salaire_calcule_net', $details)) {
            $details['salaire_calcule_net'] = $bulletin->salaire_net;
        }
        $details['salaire_percu'] = $net;
        $details['salaire_renseigne_le'] = now()->toIso8601String();

        $bulletin->update([
            'salaire_net' => $net,
            'details' => $details,
        ]);

        return $bulletin->fresh(['agent', 'contrat', 'periodePaie', 'compteTresorerie', 'media']);
    }
}
