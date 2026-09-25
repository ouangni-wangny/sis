<?php

namespace App\Application\Tresorerie;

use App\Models\CompteTresorerie;
use App\Models\Depense;
use App\Models\MouvementTresorerie;
use App\Domain\Shared\Enums\SourceMouvementTresorerie;
use Illuminate\Support\Carbon;

final class GetTresorerieStatsAction
{
    /**
     * @return array<string, mixed>
     */
    public function execute(?int $mois = null, ?int $annee = null): array
    {
        $mois ??= (int) now()->month;
        $annee ??= (int) now()->year;
        $debut = Carbon::create($annee, $mois, 1)->startOfDay();
        $fin = (clone $debut)->endOfMonth();

        $comptes = CompteTresorerie::query()
            ->where('actif', true)
            ->orderBy('libelle')
            ->get()
            ->map(fn (CompteTresorerie $c) => [
                'id' => $c->id,
                'libelle' => $c->libelle,
                'type' => $c->type,
                'solde' => $c->soldeCourant(),
            ]);

        $soldeConsolide = round($comptes->sum('solde'), 2);

        $depensesMois = Depense::query()
            ->whereBetween('date_depense', [$debut->toDateString(), $fin->toDateString()])
            ->with('categorie')
            ->get();

        $depensesParCategorie = $depensesMois
            ->groupBy(fn (Depense $d) => $d->categorie?->libelle ?? 'Divers')
            ->map(fn ($rows, $label) => [
                'categorie' => $label,
                'montant' => round($rows->sum(fn (Depense $d) => (float) $d->montant), 2),
                'count' => $rows->count(),
            ])
            ->values();

        $paieMouvements = MouvementTresorerie::query()
            ->where('source_type', SourceMouvementTresorerie::BulletinPaie->value)
            ->where('direction', 'sortie')
            ->whereBetween('date_mouvement', [$debut->toDateString(), $fin->toDateString()])
            ->get();

        $masseSalarialePayee = round($paieMouvements->sum(fn ($m) => (float) $m->montant), 2);

        $paieParMode = $paieMouvements
            ->groupBy(fn ($m) => (string) ($m->mode ?? 'autre'))
            ->map(fn ($rows, $mode) => [
                'mode' => $mode,
                'montant' => round($rows->sum(fn ($m) => (float) $m->montant), 2),
                'count' => $rows->count(),
            ])
            ->values();

        $paieParCompte = $paieMouvements
            ->groupBy('compte_tresorerie_id')
            ->map(function ($rows, $compteId) {
                $compte = CompteTresorerie::query()->find($compteId);

                return [
                    'compte_id' => $compteId,
                    'compte_libelle' => $compte?->libelle,
                    'montant' => round($rows->sum(fn ($m) => (float) $m->montant), 2),
                    'count' => $rows->count(),
                ];
            })
            ->values();

        $mouvementsMois = MouvementTresorerie::query()
            ->whereBetween('date_mouvement', [$debut->toDateString(), $fin->toDateString()])
            ->get();

        $entrees = $mouvementsMois->where('direction', 'entree');
        $sorties = $mouvementsMois->where('direction', 'sortie');

        $encaissements = $entrees->filter(
            fn ($m) => $m->source_type === SourceMouvementTresorerie::FacturePaiement,
        );

        return [
            'periode' => ['mois' => $mois, 'annee' => $annee],
            'solde_consolide' => $soldeConsolide,
            'comptes' => $comptes,
            'entrees_mois' => [
                'total' => round($entrees->sum(fn ($m) => (float) $m->montant), 2),
                'count' => $entrees->count(),
                'encaissements' => round($encaissements->sum(fn ($m) => (float) $m->montant), 2),
            ],
            'sorties_mois' => [
                'total' => round($sorties->sum(fn ($m) => (float) $m->montant), 2),
                'count' => $sorties->count(),
            ],
            'depenses_mois' => [
                'total' => round($depensesMois->sum(fn (Depense $d) => (float) $d->montant), 2),
                'par_categorie' => $depensesParCategorie,
            ],
            'paie_mois' => [
                'total_paye' => $masseSalarialePayee,
                'par_mode' => $paieParMode,
                'par_compte' => $paieParCompte,
            ],
        ];
    }
}
