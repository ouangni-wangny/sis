<?php

namespace App\Application\Tresorerie;

use App\Domain\Shared\Enums\DirectionMouvementTresorerie;
use App\Domain\Shared\Enums\SourceMouvementTresorerie;
use App\Domain\Shared\Enums\StatutDepense;
use App\Models\Depense;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreateDepenseAction
{
    public function __construct(
        private readonly PosterMouvementTresorerieAction $poster,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): Depense
    {
        return DB::transaction(function () use ($data) {
            $reference = trim((string) ($data['reference'] ?? ''));
            if ($reference === '') {
                $reference = $this->generateReference();
            }
            $data['reference'] = $reference;

            $depense = Depense::query()->create([
                ...$data,
                'statut' => StatutDepense::Validee,
                'user_id' => Auth::id(),
            ]);

            $this->poster->execute([
                'compte_tresorerie_id' => $depense->compte_tresorerie_id,
                'direction' => DirectionMouvementTresorerie::Sortie,
                'montant' => $depense->montant,
                'date_mouvement' => $depense->date_depense->format('Y-m-d'),
                'mode' => $depense->mode,
                'source_type' => SourceMouvementTresorerie::Depense,
                'source_id' => $depense->id,
                'reference' => $depense->reference,
                'notes' => $depense->libelle,
            ]);

            return $depense->load(['categorie', 'compte']);
        });
    }

    private function generateReference(): string
    {
        return 'DEP-'.now()->format('Ymd').'-'.Str::upper(Str::random(5));
    }
}
