<?php

namespace App\Application\Tresorerie;

use App\Domain\Shared\Enums\DirectionMouvementTresorerie;
use App\Domain\Shared\Enums\SourceMouvementTresorerie;
use App\Models\CompteTresorerie;
use App\Models\MouvementTresorerie;
use App\Support\FeatureFlagRegistry;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

final class PosterMouvementTresorerieAction
{
    /**
     * @param  array{
     *   compte_tresorerie_id: string,
     *   direction: DirectionMouvementTresorerie|string,
     *   montant: float|string,
     *   date_mouvement: string,
     *   mode: string,
     *   source_type: SourceMouvementTresorerie|string,
     *   source_id?: string|null,
     *   reference?: string|null,
     *   notes?: string|null,
     * }  $data
     */
    public function execute(array $data): MouvementTresorerie
    {
        $compte = CompteTresorerie::query()->findOrFail($data['compte_tresorerie_id']);
        if (! $compte->actif) {
            throw ValidationException::withMessages([
                'compte_tresorerie_id' => 'Ce compte de trésorerie est inactif.',
            ]);
        }

        $montant = round((float) $data['montant'], 2);
        if ($montant <= 0) {
            throw ValidationException::withMessages([
                'montant' => 'Le montant doit être supérieur à 0.',
            ]);
        }

        $direction = $data['direction'] instanceof DirectionMouvementTresorerie
            ? $data['direction']
            : DirectionMouvementTresorerie::from((string) $data['direction']);

        $sourceType = $data['source_type'] instanceof SourceMouvementTresorerie
            ? $data['source_type']
            : SourceMouvementTresorerie::from((string) $data['source_type']);

        $mode = (string) $data['mode'];

        $sourceId = $data['source_id'] ?? null;
        if ($sourceId && $sourceType !== SourceMouvementTresorerie::Ajustement) {
            $exists = MouvementTresorerie::query()
                ->where('source_type', $sourceType->value)
                ->where('source_id', $sourceId)
                ->where('direction', $direction->value)
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'source_id' => 'Un mouvement existe déjà pour cette source.',
                ]);
            }
        }

        return MouvementTresorerie::query()->create([
            'compte_tresorerie_id' => $compte->id,
            'direction' => $direction,
            'montant' => $montant,
            'date_mouvement' => $data['date_mouvement'],
            'mode' => $mode,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'reference' => $data['reference'] ?? null,
            'notes' => $data['notes'] ?? null,
            'user_id' => Auth::id(),
        ]);
    }

    public function reverseForSource(
        SourceMouvementTresorerie $sourceType,
        string $sourceId,
        ?string $notes = null,
    ): ?MouvementTresorerie {
        $original = MouvementTresorerie::query()
            ->where('source_type', $sourceType->value)
            ->where('source_id', $sourceId)
            ->latest()
            ->first();

        if (! $original) {
            return null;
        }

        // Annulation comptable : on garde le mouvement d’origine et on poste l’inverse.
        // Ne pas soft-deleter l’original — sinon le solde serait corrigé deux fois
        // (disparition de l’original + entrée/sortie inverse).
        return MouvementTresorerie::query()->create([
            'compte_tresorerie_id' => $original->compte_tresorerie_id,
            'direction' => $original->direction->inverse(),
            'montant' => $original->montant,
            'date_mouvement' => now()->toDateString(),
            'mode' => $original->mode,
            'source_type' => SourceMouvementTresorerie::Ajustement,
            'source_id' => null,
            'reference' => $original->reference,
            'notes' => $notes ?? 'Annulation '.$sourceType->label().' #'.substr($sourceId, 0, 8),
            'user_id' => Auth::id(),
        ]);
    }

    /**
     * Retire le mouvement lié à une source (soft-delete) sans poster d’inverse.
     * À utiliser avant un re-post (ex. correction d’encaissement).
     */
    public function retractForSource(
        SourceMouvementTresorerie $sourceType,
        string $sourceId,
    ): void {
        MouvementTresorerie::query()
            ->where('source_type', $sourceType->value)
            ->where('source_id', $sourceId)
            ->get()
            ->each(fn (MouvementTresorerie $m) => $m->delete());
    }

    public static function isModuleEnabled(): bool
    {
        return FeatureFlagRegistry::enabled('module.tresorerie');
    }
}
