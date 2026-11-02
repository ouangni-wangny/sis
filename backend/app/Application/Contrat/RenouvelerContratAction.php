<?php

namespace App\Application\Contrat;

use App\Models\Contrat;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class RenouvelerContratAction
{
    public function __construct(
        private readonly SyncContratRemunerationAction $syncRemuneration,
        private readonly GenerateContratReferenceAction $generateReference,
        private readonly HandleContratActivationAction $activation,
    ) {}

    /**
     * Reconduit un contrat aux nouveaux termes saisis, pour le même agent.
     * L’ancien contrat reste dans l’historique et ses bulletins lui restent liés.
     *
     * @param  array<string, mixed>  $data
     */
    public function execute(Contrat $source, array $data): Contrat
    {
        return DB::transaction(function () use ($source, $data): Contrat {
            $debut = Carbon::parse((string) $data['date_debut'])->startOfDay();

            $data['agent_id'] = $source->agent_id;
            $data['contrat_parent_id'] = $source->id;
            $data['statut'] = 'actif';

            if (blank($data['reference'] ?? null)) {
                $data['reference'] = $this->generateReference->execute(
                    (string) $data['type'],
                    $debut->format('Y'),
                );
            }

            $this->cloreSource($source, $debut);

            $nouveau = Contrat::query()->create(
                $this->syncRemuneration->prepare($data, $source->agent),
            );

            $this->activation->execute($nouveau->refresh());

            return $nouveau;
        });
    }

    /**
     * Pas de HandleContratClotureAction ici : l’agent garde un contrat valide,
     * donc ses périmètres, son statut et ses vacations ne doivent pas bouger.
     * Un contrat qui court encore jusqu’à sa fin sera clôturé par le job quotidien.
     */
    private function cloreSource(Contrat $source, Carbon $debut): void
    {
        if ($source->date_fin === null || $source->date_fin->gte($debut)) {
            $source->date_fin = $debut->copy()->subDay();
        }

        if (
            in_array($source->statut, ['actif', 'suspendu'], true)
            && $source->date_fin->lt(now()->startOfDay())
        ) {
            $source->statut = 'termine';
        }

        $source->save();
    }
}
