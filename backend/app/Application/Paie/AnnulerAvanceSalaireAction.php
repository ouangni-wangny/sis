<?php

namespace App\Application\Paie;

use App\Application\Tresorerie\PosterMouvementTresorerieAction;
use App\Domain\Shared\Enums\SourceMouvementTresorerie;
use App\Domain\Shared\Enums\StatutAvanceSalaire;
use App\Models\AvanceSalaire;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AnnulerAvanceSalaireAction
{
    public function __construct(
        private readonly PosterMouvementTresorerieAction $poster,
    ) {}

    public function execute(AvanceSalaire $avance, ?string $motif = null): AvanceSalaire
    {
        return DB::transaction(function () use ($avance, $motif) {
            $avance = AvanceSalaire::query()->lockForUpdate()->findOrFail($avance->id);

            if ($avance->statut !== StatutAvanceSalaire::EnCours) {
                throw ValidationException::withMessages([
                    'statut' => $avance->statut === StatutAvanceSalaire::Deduite
                        ? 'Cette avance est déjà retenue sur un bulletin payé.'
                        : 'Cette avance est déjà annulée.',
                ]);
            }

            $avance->update([
                'statut' => StatutAvanceSalaire::Annulee,
                'annulee_le' => now(),
                'motif_annulation' => $motif,
            ]);

            // L’argent revient sur le compte : le mouvement d’origine reste, on poste l’inverse.
            $this->poster->reverseForSource(
                SourceMouvementTresorerie::AvanceSalaire,
                $avance->id,
                'Annulation avance sur salaire '.$avance->reference,
            );

            return $avance;
        });
    }
}
