<?php

namespace App\Application\Paie;

use App\Domain\Shared\Enums\StatutBulletinPaie;
use App\Models\BulletinPaie;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RenseignerSalairePercuBulkAction
{
    /**
     * @param  list<array{id: string, salaire_net: float|int|string}>  $items
     * @return array{updated: int}
     */
    public function execute(array $items): array
    {
        if ($items === []) {
            throw ValidationException::withMessages([
                'items' => 'Aucun bulletin à mettre à jour.',
            ]);
        }

        $ids = array_values(array_unique(array_map(
            fn (array $item) => (string) $item['id'],
            $items
        )));

        /** @var \Illuminate\Support\Collection<string, BulletinPaie> $bulletins */
        $bulletins = BulletinPaie::query()
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        if ($bulletins->count() !== count($ids)) {
            throw ValidationException::withMessages([
                'items' => 'Un ou plusieurs bulletins sont introuvables.',
            ]);
        }

        $byId = [];
        foreach ($items as $item) {
            $id = (string) $item['id'];
            $net = round((float) $item['salaire_net'], 2);
            if ($net <= 0) {
                throw ValidationException::withMessages([
                    'items' => 'Chaque salaire perçu doit être supérieur à 0.',
                ]);
            }
            $byId[$id] = $net;
        }

        $now = now()->toIso8601String();

        DB::transaction(function () use ($bulletins, $byId, $now) {
            foreach ($byId as $id => $net) {
                /** @var BulletinPaie $bulletin */
                $bulletin = $bulletins->get($id);

                if ($bulletin->statut === StatutBulletinPaie::Paye) {
                    throw ValidationException::withMessages([
                        'items' => 'Impossible de modifier un bulletin déjà payé.',
                    ]);
                }

                $details = is_array($bulletin->details) ? $bulletin->details : [];
                if (! array_key_exists('salaire_calcule_net', $details)) {
                    $details['salaire_calcule_net'] = $bulletin->salaire_net;
                }
                $details['salaire_percu'] = $net;
                $details['salaire_renseigne_le'] = $now;

                $bulletin->update([
                    'salaire_net' => $net,
                    'details' => $details,
                ]);
            }
        });

        return ['updated' => count($byId)];
    }
}
