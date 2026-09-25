<?php

namespace App\Application\Tresorerie;

use App\Domain\Shared\Enums\SourceMouvementTresorerie;
use App\Models\MouvementTresorerie;
use Illuminate\Support\Facades\DB;

final class CreateAjustementTresorerieAction
{
    public function __construct(
        private readonly PosterMouvementTresorerieAction $poster,
    ) {}

    /**
     * @param  array{
     *   compte_tresorerie_id: string,
     *   direction: string,
     *   montant: float|string,
     *   date_mouvement: string,
     *   mode?: string,
     *   reference?: string|null,
     *   notes?: string|null,
     * }  $data
     */
    public function execute(array $data): MouvementTresorerie
    {
        return DB::transaction(fn () => $this->poster->execute([
            'compte_tresorerie_id' => $data['compte_tresorerie_id'],
            'direction' => $data['direction'],
            'montant' => $data['montant'],
            'date_mouvement' => $data['date_mouvement'],
            'mode' => $data['mode'] ?? 'autre',
            'source_type' => SourceMouvementTresorerie::Ajustement,
            'source_id' => null,
            'reference' => $data['reference'] ?? null,
            'notes' => $data['notes'] ?? 'Ajustement manuel',
        ]));
    }
}
