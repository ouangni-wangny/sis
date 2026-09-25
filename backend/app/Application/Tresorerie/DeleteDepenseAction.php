<?php

namespace App\Application\Tresorerie;

use App\Domain\Shared\Enums\SourceMouvementTresorerie;
use App\Models\Depense;
use Illuminate\Support\Facades\DB;

final class DeleteDepenseAction
{
    public function __construct(
        private readonly PosterMouvementTresorerieAction $poster,
    ) {}

    public function execute(Depense $depense): void
    {
        DB::transaction(function () use ($depense) {
            $this->poster->reverseForSource(
                SourceMouvementTresorerie::Depense,
                $depense->id,
                'Annulation dépense : '.$depense->libelle,
            );
            $depense->delete();
        });
    }
}
