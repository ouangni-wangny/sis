<?php

namespace App\Application\Paie;

use App\Application\Tresorerie\PosterMouvementTresorerieAction;
use App\Domain\Paie\SituationSalaireMois;
use App\Domain\Shared\Enums\DirectionMouvementTresorerie;
use App\Domain\Shared\Enums\SourceMouvementTresorerie;
use App\Domain\Shared\Enums\StatutAvanceSalaire;
use App\Models\Agent;
use App\Models\AvanceSalaire;
use App\Models\BulletinPaie;
use App\Models\Contrat;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CreerAvanceSalaireAction
{
    public function __construct(
        private readonly PosterMouvementTresorerieAction $poster,
    ) {}

    /**
     * Le cash sort à la remise de l’avance ; le règlement du bulletin ne sortira que le reste.
     *
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): AvanceSalaire
    {
        return DB::transaction(function () use ($data) {
            $agent = Agent::query()->lockForUpdate()->findOrFail($data['agent_id']);
            $mois = (int) $data['mois'];
            $annee = (int) $data['annee'];
            $montant = round((float) $data['montant'], 2);

            $this->verifier($agent, $mois, $annee, $montant);

            $reference = trim((string) ($data['reference'] ?? ''));
            if ($reference === '') {
                $reference = 'AVS-'.now()->format('Ymd').'-'.Str::upper(Str::random(5));
            }

            $avance = AvanceSalaire::query()->create([
                ...$data,
                'montant' => $montant,
                'reference' => $reference,
                'statut' => StatutAvanceSalaire::EnCours,
                'user_id' => Auth::id(),
            ]);

            if (PosterMouvementTresorerieAction::isModuleEnabled()) {
                $this->poster->execute([
                    'compte_tresorerie_id' => $avance->compte_tresorerie_id,
                    'direction' => DirectionMouvementTresorerie::Sortie,
                    'montant' => $montant,
                    'date_mouvement' => $avance->date_avance->format('Y-m-d'),
                    'mode' => $avance->mode,
                    'source_type' => SourceMouvementTresorerie::AvanceSalaire,
                    'source_id' => $avance->id,
                    'reference' => $reference,
                    'notes' => sprintf(
                        'Avance sur salaire %02d/%d — %s %s (%s)',
                        $mois,
                        $annee,
                        $agent->prenom,
                        $agent->nom,
                        $agent->matricule,
                    ),
                ]);
            }

            return $avance;
        });
    }

    private function verifier(Agent $agent, int $mois, int $annee, float $montant): void
    {
        $situation = SituationSalaireMois::pour($agent, $mois, $annee);

        if ($situation['bulletin_paye']) {
            throw ValidationException::withMessages([
                'mois' => 'Le salaire de ce mois est déjà payé : choisissez un autre mois de retenue.',
            ]);
        }

        $aUnBulletin = BulletinPaie::query()
            ->where('agent_id', $agent->id)
            ->whereHas('periodePaie', fn ($q) => $q->where('mois', $mois)->where('annee', $annee))
            ->exists();

        if (! $aUnBulletin && ! Contrat::query()->valide()->where('agent_id', $agent->id)->exists()) {
            throw ValidationException::withMessages([
                'agent_id' => 'Cet agent n’a ni contrat valide ni bulletin sur ce mois.',
            ]);
        }

        $net = $situation['salaire_net'];
        if ($net !== null && round($situation['avances_total'] + $montant, 2) > $net) {
            throw ValidationException::withMessages([
                'montant' => sprintf(
                    'Le total des avances dépasserait le salaire net du mois (%s FCFA, déjà donné : %s FCFA).',
                    number_format($net, 0, ',', ' '),
                    number_format($situation['avances_total'], 0, ',', ' '),
                ),
            ]);
        }
    }
}
