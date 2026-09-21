<?php

namespace App\Application\Reporting;

use App\Domain\Shared\Enums\PeriodiciteFacturation;
use App\Domain\Shared\Enums\StatutAbonnement;
use App\Domain\Shared\Enums\StatutAgent;
use App\Domain\Shared\Enums\StatutAnomalie;
use App\Domain\Shared\Enums\StatutFacture;
use App\Domain\Shared\Enums\StatutVacation;
use App\Models\Abonnement;
use App\Models\Agent;
use App\Models\Anomalie;
use App\Models\Absence;
use App\Models\BulletinPaie;
use App\Models\Contrat;
use App\Models\Controle;
use App\Models\Facture;
use App\Models\Paiement;
use App\Models\Ronde;
use App\Models\Site;
use App\Models\Vacation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class GetDashboardStatsAction
{
    public function __construct(private GetPosteCoverageAction $coverage) {}

    public function execute(?string $from = null, ?string $to = null, ?string $zoneId = null, ?string $clientId = null): array
    {
        $from = $from ? Carbon::parse($from)->startOfDay() : now()->startOfDay();
        $to = $to ? Carbon::parse($to)->endOfDay() : now()->endOfDay();
        $today = now()->toDateString();

        $sitesQuery = Site::query()
            ->when($zoneId, fn ($q) => $q->where('zone_id', $zoneId))
            ->when($clientId, fn ($q) => $q->where('client_id', $clientId));

        $siteIds = (clone $sitesQuery)->pluck('id');

        $controles7j = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = now()->subDays($i)->toDateString();
            $controles7j[] = [
                'date' => $day,
                'count' => Controle::query()
                    ->whereDate('effectue_at', $day)
                    ->when($siteIds->isNotEmpty() || $zoneId || $clientId, fn ($q) => $q->whereIn('site_id', $siteIds))
                    ->count(),
            ];
        }

        $agentsParStatut = [];
        foreach (StatutAgent::cases() as $statut) {
            $agentsParStatut[$statut->value] = Agent::query()->where('statut', $statut)->count();
        }

        $anomaliesParType = Anomalie::query()
            ->when($siteIds->isNotEmpty() || $zoneId || $clientId, fn ($q) => $q->whereIn('site_id', $siteIds))
            ->where('signale_at', '>=', now()->subDays(30))
            ->selectRaw('type, count(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type')
            ->all();

        $coverage = $this->coverage->execute($today, null);
        if ($siteIds->isNotEmpty() || $zoneId || $clientId) {
            $coverage = $coverage->filter(
                fn ($row) => $row['site'] && $siteIds->contains($row['site']['id'])
            )->values();
        }
        $couvertureProblemes = $coverage->filter(fn ($r) => $r['statut'] !== 'ok')->values();

        $soon = now()->addDays(30)->toDateString();
        $docsExpirants = Agent::query()
            ->with('contratActif')
            ->where(function ($q) use ($soon) {
                $q->whereHas('contratActif', function ($q2) use ($soon) {
                    $q2->whereNotNull('date_fin')
                        ->whereDate('date_fin', '<=', $soon);
                })->orWhere(function ($q2) use ($soon) {
                    $q2->whereNotNull('date_expiration_permis')
                        ->whereDate('date_expiration_permis', '<=', $soon);
                });
            })
            ->limit(15)
            ->get(['id', 'matricule', 'nom', 'prenom', 'date_expiration_permis'])
            ->map(fn (Agent $a) => [
                'id' => $a->id,
                'matricule' => $a->matricule,
                'nom' => $a->nom,
                'prenom' => $a->prenom,
                'date_expiration_contrat' => $a->contratActif?->date_fin?->format('Y-m-d'),
                'date_expiration_permis' => $a->date_expiration_permis?->format('Y-m-d'),
            ])
            ->sortBy(fn ($row) => $row['date_expiration_contrat'] ?? $row['date_expiration_permis'] ?? '9999-12-31')
            ->values()
            ->all();

        $anomaliesATraiter = Anomalie::query()
            ->with('site:id,nom')
            ->where('statut', '!=', StatutAnomalie::Resolue)
            ->when($siteIds->isNotEmpty() || $zoneId || $clientId, fn ($q) => $q->whereIn('site_id', $siteIds))
            ->latest('signale_at')
            ->limit(10)
            ->get()
            ->map(fn (Anomalie $a) => [
                'id' => $a->id,
                'type' => $a->type?->value ?? (string) $a->type,
                'gravite' => $a->gravite?->value ?? (string) $a->gravite,
                'statut' => $a->statut?->value ?? (string) $a->statut,
                'signale_at' => $a->signale_at?->toIso8601String(),
                'site' => $a->site ? ['id' => $a->site->id, 'nom' => $a->site->nom] : null,
            ])
            ->all();

        $vacationsDuJour = Vacation::query()
            ->with(['agent:id,nom,prenom,matricule', 'site:id,nom', 'poste:id,nom'])
            ->whereNotIn('statut', [StatutVacation::Annulee])
            ->whereDate('date_debut', '<=', $today)
            ->where(function ($q) use ($today) {
                $q->whereNull('date_fin')->orWhereDate('date_fin', '>=', $today);
            })
            ->when($siteIds->isNotEmpty() || $zoneId || $clientId, fn ($q) => $q->whereIn('site_id', $siteIds))
            ->orderBy('heure_debut')
            ->limit(20)
            ->get()
            ->map(fn (Vacation $v) => [
                'id' => $v->id,
                'heure_debut' => $v->heure_debut,
                'heure_fin' => $v->heure_fin,
                'statut' => $v->statut?->value ?? (string) $v->statut,
                'agent' => $v->agent ? [
                    'id' => $v->agent->id,
                    'nom' => $v->agent->nom,
                    'prenom' => $v->agent->prenom,
                    'matricule' => $v->agent->matricule,
                ] : null,
                'site' => $v->site ? ['id' => $v->site->id, 'nom' => $v->site->nom] : null,
                'poste' => $v->poste ? ['id' => $v->poste->id, 'nom' => $v->poste->nom] : null,
            ])
            ->all();

        $controleCoverage = $this->controleCoverage($today, $siteIds, $zoneId, $clientId);

        $commercial = $this->commercialStats($clientId);

        $contratsActifs = Contrat::query()->where('statut', 'actif')->count();
        $masseSalariale = (float) Contrat::query()
            ->where('statut', 'actif')
            ->sum('salaire_net');
        $absencesEnAttente = Absence::query()
            ->where('statut', 'en_attente')
            ->count();
        $contratsAlertes = app(\App\Application\Rh\GetContratsAlertsAction::class)->execute();

        return [
            'agents_disponibles' => Agent::query()->where('statut', StatutAgent::Disponible)->count(),
            'agents_actifs' => Agent::query()->whereIn('statut', [StatutAgent::Disponible, StatutAgent::EnActivite])->count(),
            'vacations_actives' => Vacation::query()
                ->whereIn('statut', [StatutVacation::Planifiee, StatutVacation::EnCours])
                ->when($siteIds->isNotEmpty() || $zoneId || $clientId, fn ($q) => $q->whereIn('site_id', $siteIds))
                ->count(),
            'rondes_du_jour' => Ronde::query()
                ->whereDate('created_at', today())
                ->when($siteIds->isNotEmpty() || $zoneId || $clientId, fn ($q) => $q->whereIn('site_id', $siteIds))
                ->count(),
            'controles_du_jour' => Controle::query()
                ->whereBetween('effectue_at', [$from, $to])
                ->when($siteIds->isNotEmpty() || $zoneId || $clientId, fn ($q) => $q->whereIn('site_id', $siteIds))
                ->count(),
            'anomalies_ouvertes' => Anomalie::query()
                ->where('statut', '!=', StatutAnomalie::Resolue)
                ->when($siteIds->isNotEmpty() || $zoneId || $clientId, fn ($q) => $q->whereIn('site_id', $siteIds))
                ->count(),
            'sites_surveilles' => $sitesQuery->count(),
            'postes_sous_effectif' => $couvertureProblemes->count(),
            'docs_expirants_count' => count($docsExpirants),
            'controles_7j' => $controles7j,
            'controle_coverage' => $controleCoverage,
            'agents_par_statut' => $agentsParStatut,
            'anomalies_par_type' => $anomaliesParType,
            'commercial' => $commercial,
            'rh' => [
                'contrats_actifs' => $contratsActifs,
                'masse_salariale' => $masseSalariale,
                'absences_en_attente' => $absencesEnAttente,
                'contrats_alertes_count' => count($contratsAlertes),
                'contrats_alertes' => array_slice($contratsAlertes, 0, 10),
            ],
            'aujourdhui' => [
                'anomalies_a_traiter' => $anomaliesATraiter,
                'vacations' => $vacationsDuJour,
                'couverture_problemes' => $couvertureProblemes->take(15)->all(),
                'docs_expirants' => $docsExpirants,
            ],
        ];
    }

    /**
     * Taux de sites effectivement contrôlés aujourd'hui, parmi ceux qui ont
     * au moins un agent en poste — sert de preuve de service côté client
     * et d'alerte opérationnelle (site jamais visité de la journée).
     *
     * @param  Collection<int, string>  $siteIds
     * @return array<string, mixed>
     */
    private function controleCoverage(string $today, Collection $siteIds, ?string $zoneId, ?string $clientId): array
    {
        $siteIdsAvecVacation = Vacation::query()
            ->whereIn('statut', [StatutVacation::Planifiee, StatutVacation::EnCours])
            ->whereDate('date_debut', '<=', $today)
            ->where(function ($q) use ($today) {
                $q->whereNull('date_fin')->orWhereDate('date_fin', '>=', $today);
            })
            ->when($siteIds->isNotEmpty() || $zoneId || $clientId, fn ($q) => $q->whereIn('site_id', $siteIds))
            ->distinct()
            ->pluck('site_id');

        $siteIdsControles = Controle::query()
            ->whereDate('effectue_at', $today)
            ->whereIn('site_id', $siteIdsAvecVacation)
            ->distinct()
            ->pluck('site_id');

        $siteIdsSansControle = $siteIdsAvecVacation->diff($siteIdsControles)->values();

        $sitesSansControle = Site::query()
            ->whereIn('id', $siteIdsSansControle)
            ->limit(15)
            ->get(['id', 'nom'])
            ->map(fn (Site $s) => ['id' => $s->id, 'nom' => $s->nom])
            ->all();

        return [
            'sites_actifs_aujourdhui' => $siteIdsAvecVacation->count(),
            'sites_controles_aujourdhui' => $siteIdsControles->count(),
            'taux_couverture_pct' => $siteIdsAvecVacation->isNotEmpty()
                ? (int) round($siteIdsControles->count() / $siteIdsAvecVacation->count() * 100)
                : 100,
            'sites_sans_controle' => $sitesSansControle,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function commercialStats(?string $clientId): array
    {
        $facturesValides = Facture::query()
            ->where('statut', StatutFacture::Valide)
            ->when($clientId, fn ($q) => $q->where('client_id', $clientId))
            ->withSum('paiements as paiements_sum_montant', 'montant')
            ->get(['id', 'montant_ttc', 'periodicite']);

        $montantTtc = round((float) $facturesValides->sum(fn ($f) => (float) $f->montant_ttc), 2);
        $montantPaye = round((float) $facturesValides->sum(
            fn ($f) => (float) ($f->paiements_sum_montant ?? 0)
        ), 2);
        $montantImpaye = round(max(0, $montantTtc - $montantPaye), 2);

        $parPaiement = [
            'non_payee' => 0,
            'partiel' => 0,
            'soldee' => 0,
        ];

        foreach ($facturesValides as $facture) {
            $paye = round((float) ($facture->paiements_sum_montant ?? 0), 2);
            $ttc = round((float) $facture->montant_ttc, 2);
            if ($paye <= 0) {
                $parPaiement['non_payee']++;
            } elseif ($paye + 1 >= $ttc) {
                $parPaiement['soldee']++;
            } else {
                $parPaiement['partiel']++;
            }
        }

        $parPeriodicite = [];
        foreach (PeriodiciteFacturation::cases() as $periodicite) {
            $subset = $facturesValides->filter(
                fn ($f) => ($f->periodicite?->value ?? $f->periodicite) === $periodicite->value
            );
            $parPeriodicite[$periodicite->value] = [
                'count' => $subset->count(),
                'montant_ttc' => round((float) $subset->sum(fn ($f) => (float) $f->montant_ttc), 2),
                'montant_paye' => round((float) $subset->sum(
                    fn ($f) => (float) ($f->paiements_sum_montant ?? 0)
                ), 2),
            ];
        }

        $encaissements7j = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = now()->timezone('Africa/Abidjan')->subDays($i)->toDateString();
            $montant = (float) Paiement::query()
                ->whereDate('date_paiement', $day)
                ->when(
                    $clientId,
                    fn ($q) => $q->whereHas(
                        'facture',
                        fn ($fq) => $fq->where('client_id', $clientId)
                    )
                )
                ->sum('montant');
            $encaissements7j[] = [
                'date' => $day,
                'montant' => round($montant, 2),
            ];
        }

        $prochainesFactures = Abonnement::query()
            ->where('statut', StatutAbonnement::Actif)
            ->whereNotNull('prochaine_facture_le')
            ->when($clientId, fn ($q) => $q->where('client_id', $clientId))
            ->whereDate('prochaine_facture_le', '<=', now()->addDays(30)->toDateString())
            ->count();

        return [
            'proformas_en_attente' => Facture::query()
                ->where('statut', StatutFacture::EnAttente)
                ->when($clientId, fn ($q) => $q->where('client_id', $clientId))
                ->count(),
            'factures_validees' => $facturesValides->count(),
            'abonnements_actifs' => Abonnement::query()
                ->where('statut', StatutAbonnement::Actif)
                ->when($clientId, fn ($q) => $q->where('client_id', $clientId))
                ->count(),
            'montant_facture_ttc' => $montantTtc,
            'montant_paye' => $montantPaye,
            'montant_impaye' => $montantImpaye,
            'factures_par_paiement' => $parPaiement,
            'factures_par_periodicite' => $parPeriodicite,
            'encaissements_7j' => $encaissements7j,
            'abonnements_echeance_30j' => $prochainesFactures,
        ];
    }
}
