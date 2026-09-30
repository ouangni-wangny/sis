<?php

namespace App\Application\Tresorerie;

use App\Models\CompteTresorerie;
use App\Domain\Shared\Enums\DirectionMouvementTresorerie;
use App\Domain\Shared\Enums\StatutDepense;
use App\Domain\Shared\Enums\StatutFacture;
use App\Models\Depense;
use App\Models\Facture;
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
            ->where('statut', StatutDepense::Validee)
            ->whereDate('date_depense', '>=', $debut->toDateString())
            ->whereDate('date_depense', '<=', $fin->toDateString())
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
            ->whereDate('date_mouvement', '>=', $debut->toDateString())
            ->whereDate('date_mouvement', '<=', $fin->toDateString())
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

        // Les transferts internes s'annulent (sortie + entrée) : ils ne sont ni des recettes ni des charges.
        $mouvementsMois = MouvementTresorerie::query()
            ->whereDate('date_mouvement', '>=', $debut->toDateString())
            ->whereDate('date_mouvement', '<=', $fin->toDateString())
            ->where('source_type', '!=', SourceMouvementTresorerie::Transfert->value)
            ->get();

        // Entrées = encaissements facture uniquement (pas transferts / retours / ajustements).
        $entrees = $mouvementsMois->filter(
            fn ($m) => $m->direction?->value === 'entree'
                && $m->source_type === SourceMouvementTresorerie::FacturePaiement,
        );
        $sorties = $mouvementsMois->where('direction', DirectionMouvementTresorerie::Sortie);
        $retours = $mouvementsMois->where('direction', DirectionMouvementTresorerie::Retour);

        // CA = factures validées émises sur le mois (date_emission), hors proformas / annulées.
        $facturesEmises = Facture::query()
            ->where('statut', StatutFacture::Valide)
            ->whereDate('date_emission', '>=', $debut->toDateString())
            ->whereDate('date_emission', '<=', $fin->toDateString())
            ->get();

        $caHt = round($facturesEmises->sum(fn (Facture $f) => (float) $f->montant_ht), 2);
        $caTtc = round($facturesEmises->sum(fn (Facture $f) => (float) $f->montant_ttc), 2);

        $entreesTotal = round($entrees->sum(fn ($m) => (float) $m->montant), 2);

        return [
            'periode' => ['mois' => $mois, 'annee' => $annee],
            'solde_consolide' => $soldeConsolide,
            'comptes' => $comptes,
            'chiffre_affaires_mois' => [
                'total_ttc' => $caTtc,
                'total_ht' => $caHt,
                'count' => $facturesEmises->count(),
            ],
            'entrees_mois' => [
                'total' => $entreesTotal,
                'count' => $entrees->count(),
                'encaissements' => $entreesTotal,
            ],
            'sorties_mois' => [
                'total' => round($sorties->sum(fn ($m) => (float) $m->montant), 2),
                'count' => $sorties->count(),
            ],
            'retours_mois' => [
                'total' => round($retours->sum(fn ($m) => (float) $m->montant), 2),
                'count' => $retours->count(),
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
