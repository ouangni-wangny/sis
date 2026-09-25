<?php

namespace App\Application\Tresorerie;

use App\Domain\Shared\Enums\SourceMouvementTresorerie;
use App\Domain\Shared\Enums\StatutDepense;
use App\Models\Depense;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DeleteDepenseAction
{
    public function __construct(
        private readonly PosterMouvementTresorerieAction $poster,
    ) {}

    public function execute(Depense $depense): Depense
    {
        if ($depense->isAnnulee()) {
            throw ValidationException::withMessages([
                'depense' => 'Cette dépense est déjà annulée.',
            ]);
        }

        return DB::transaction(function () use ($depense) {
            $this->poster->reverseForSource(
                SourceMouvementTresorerie::Depense,
                $depense->id,
                'Annulation dépense : '.$depense->libelle,
            );

            $depense->update(['statut' => StatutDepense::Annulee]);

            return $depense->fresh()->load(['categorie', 'compte', 'media']);
        });
    }
}
