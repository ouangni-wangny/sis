<?php

namespace App\Application\Reporting;

use App\Models\Agent;
use App\Models\Anomalie;
use App\Models\Absence;
use App\Models\BulletinPaie;
use App\Models\Contrat;
use App\Models\Controle;
use App\Models\RapportExport;
use App\Models\Ronde;
use App\Models\RondeCheckpoint;
use App\Models\Vacation;
use Carbon\Carbon;

final class BuildRapportDatasetAction
{
    /**
     * @return array{
     *   title: string,
     *   subtitle: string,
     *   period_label: string|null,
     *   columns: list<string>,
     *   rows: list<list<string>>,
     *   summary: list<array{label: string, value: string}>
     * }
     */
    public function execute(RapportExport $rapport): array
    {
        $filtres = $rapport->filtres ?? [];
        $from = $this->parseDate($filtres['date_debut'] ?? null);
        $to = $this->parseDate($filtres['date_fin'] ?? null);
        $periodLabel = $this->periodLabel($from, $to);

        return match ($rapport->type) {
            'agents' => $this->agents($from, $to, $periodLabel),
            'plannings' => $this->plannings($from, $to, $periodLabel),
            'pointages' => $this->pointages($from, $to, $periodLabel),
            'controles' => $this->controles($from, $to, $periodLabel),
            'anomalies' => $this->anomalies($from, $to, $periodLabel),
            'rondes' => $this->rondes($from, $to, $periodLabel),
            'contrats' => $this->contrats($from, $to, $periodLabel),
            'absences' => $this->absences($from, $to, $periodLabel),
            'paie' => $this->paie($from, $to, $periodLabel),
            default => $this->generic($rapport, $periodLabel),
        };
    }

    private function agents(?Carbon $from, ?Carbon $to, ?string $periodLabel): array
    {
        $query = Agent::query()->with('grade')->orderBy('nom')->orderBy('prenom');

        // Effectif présent à la date de fin de période.
        if ($to) {
            $query->where(function ($q) use ($to) {
                $q->whereNull('date_embauche')
                    ->orWhereDate('date_embauche', '<=', $to);
            });
        }

        $agents = $query->get();

        $rows = $agents->map(fn (Agent $a) => [
            (string) $a->matricule,
            trim($a->prenom.' '.$a->nom),
            $this->enum($a->type),
            $a->grade?->libelle ?? '—',
            $this->enum($a->statut),
            $a->telephone ?: '—',
            $a->date_embauche?->format('d/m/Y') ?? '—',
        ])->all();

        return [
            'title' => 'Rapport agents',
            'subtitle' => 'Effectif, grades et statuts',
            'period_label' => $periodLabel,
            'columns' => ['Matricule', 'Nom', 'Type', 'Grade', 'Statut', 'Téléphone', 'Embauche'],
            'rows' => $rows,
            'summary' => [
                ['label' => 'Agents listés', 'value' => (string) count($rows)],
                ['label' => 'Disponibles', 'value' => (string) $agents->filter(
                    fn (Agent $a) => $this->enum($a->statut) === 'disponible'
                )->count()],
                ['label' => 'En activité', 'value' => (string) $agents->filter(
                    fn (Agent $a) => $this->enum($a->statut) === 'en_activite'
                )->count()],
            ],
        ];
    }

    private function plannings(?Carbon $from, ?Carbon $to, ?string $periodLabel): array
    {
        $query = Vacation::query()->with(['agent', 'site', 'poste'])->orderBy('date_debut')->orderBy('heure_debut');
        if ($from) {
            $query->whereDate('date_debut', '>=', $from);
        }
        if ($to) {
            $query->whereDate('date_debut', '<=', $to);
        }

        $items = $query->get();
        $rows = $items->map(fn (Vacation $v) => [
            $v->date_debut?->format('d/m/Y') ?? '—',
            substr((string) $v->heure_debut, 0, 5).' – '.substr((string) $v->heure_fin, 0, 5),
            $v->agent ? trim($v->agent->prenom.' '.$v->agent->nom) : '—',
            $v->site?->nom ?? '—',
            $v->poste?->nom ?? '—',
            $this->enum($v->statut),
        ])->all();

        return [
            'title' => 'Rapport planning postes',
            'subtitle' => 'Affectations planifiées sur la période',
            'period_label' => $periodLabel,
            'columns' => ['Date', 'Horaires', 'Agent', 'Site', 'Poste', 'Statut'],
            'rows' => $rows,
            'summary' => [
                ['label' => 'Affectations', 'value' => (string) count($rows)],
            ],
        ];
    }

    private function pointages(?Carbon $from, ?Carbon $to, ?string $periodLabel): array
    {
        $query = RondeCheckpoint::query()
            ->with(['ronde.agent', 'ronde.site', 'checkpoint'])
            ->whereNotNull('scanne_at')
            ->orderBy('scanne_at');

        if ($from) {
            $query->whereDate('scanne_at', '>=', $from);
        }
        if ($to) {
            $query->whereDate('scanne_at', '<=', $to);
        }

        $items = $query->limit(500)->get();
        $rows = $items->map(function (RondeCheckpoint $rc) {
            $ronde = $rc->ronde;

            return [
                $rc->scanne_at?->timezone('Africa/Abidjan')->format('d/m/Y H:i') ?? '—',
                $ronde?->agent ? trim($ronde->agent->prenom.' '.$ronde->agent->nom) : '—',
                $ronde?->site?->nom ?? '—',
                $rc->checkpoint?->nom ?? $rc->checkpoint?->code_qr ?? '—',
                $rc->valide ? 'Valide' : 'Non valide',
            ];
        })->all();

        return [
            'title' => 'Rapport pointages',
            'subtitle' => 'Scans checkpoints / présences agents',
            'period_label' => $periodLabel,
            'columns' => ['Horodatage', 'Agent', 'Site', 'Checkpoint', 'Validité'],
            'rows' => $rows,
            'summary' => [
                ['label' => 'Pointages', 'value' => (string) count($rows)],
                ['label' => 'Valides', 'value' => (string) $items->where('valide', true)->count()],
            ],
        ];
    }

    private function controles(?Carbon $from, ?Carbon $to, ?string $periodLabel): array
    {
        $query = Controle::query()
            ->with(['agent', 'controleAgent', 'site', 'poste'])
            ->orderByDesc('effectue_at');
        if ($from) {
            $query->whereDate('effectue_at', '>=', $from);
        }
        if ($to) {
            $query->whereDate('effectue_at', '<=', $to);
        }

        $items = $query->limit(500)->get();
        $rows = $items->map(fn (Controle $c) => [
            $c->effectue_at?->timezone('Africa/Abidjan')->format('d/m/Y H:i') ?? '—',
            $c->controleAgent
                ? trim($c->controleAgent->prenom.' '.$c->controleAgent->nom)
                : '—',
            $c->agent ? trim($c->agent->prenom.' '.$c->agent->nom) : '—',
            $c->site?->nom ?? '—',
            $c->poste?->nom ?? '—',
            $c->commentaire ? mb_strimwidth($c->commentaire, 0, 60, '…') : '—',
        ])->all();

        return [
            'title' => 'Rapport contrôles terrain',
            'subtitle' => 'Contrôles de présence sur la période',
            'period_label' => $periodLabel,
            'columns' => ['Effectué le', 'Agent contrôlé', 'Contrôleur', 'Site', 'Poste', 'Commentaire'],
            'rows' => $rows,
            'summary' => [
                ['label' => 'Contrôles', 'value' => (string) count($rows)],
            ],
        ];
    }

    private function anomalies(?Carbon $from, ?Carbon $to, ?string $periodLabel): array
    {
        $query = Anomalie::query()->with(['signalePar', 'site'])->orderByDesc('signale_at');
        if ($from) {
            $query->whereDate('signale_at', '>=', $from);
        }
        if ($to) {
            $query->whereDate('signale_at', '<=', $to);
        }

        $items = $query->limit(500)->get();
        $rows = $items->map(fn (Anomalie $a) => [
            $a->signale_at?->timezone('Africa/Abidjan')->format('d/m/Y H:i') ?? '—',
            $this->enum($a->type),
            $this->enum($a->gravite),
            $this->enum($a->statut),
            $a->site?->nom ?? '—',
            $a->signalePar ? trim($a->signalePar->prenom.' '.$a->signalePar->nom) : '—',
            $a->commentaire ? mb_strimwidth($a->commentaire, 0, 50, '…') : '—',
        ])->all();

        return [
            'title' => 'Rapport anomalies',
            'subtitle' => 'Incidents et traitements',
            'period_label' => $periodLabel,
            'columns' => ['Signalée le', 'Type', 'Gravité', 'Statut', 'Site', 'Signalée par', 'Commentaire'],
            'rows' => $rows,
            'summary' => [
                ['label' => 'Anomalies', 'value' => (string) count($rows)],
                ['label' => 'Ouvertes', 'value' => (string) $items->filter(fn ($a) => $this->enum($a->statut) !== 'resolue' && $this->enum($a->statut) !== 'fermee')->count()],
            ],
        ];
    }

    private function rondes(?Carbon $from, ?Carbon $to, ?string $periodLabel): array
    {
        $query = Ronde::query()->with(['agent', 'site'])->orderByDesc('demarree_at');
        if ($from) {
            $query->whereDate('demarree_at', '>=', $from);
        }
        if ($to) {
            $query->whereDate('demarree_at', '<=', $to);
        }

        $items = $query->limit(500)->get();
        $rows = $items->map(fn (Ronde $r) => [
            $r->demarree_at?->timezone('Africa/Abidjan')->format('d/m/Y H:i') ?? '—',
            $r->terminee_at?->timezone('Africa/Abidjan')->format('d/m/Y H:i') ?? '—',
            $r->agent ? trim($r->agent->prenom.' '.$r->agent->nom) : '—',
            $r->site?->nom ?? '—',
            $this->enum($r->statut),
            ((string) ($r->progression ?? 0)).' %',
        ])->all();

        return [
            'title' => 'Rapport rondes',
            'subtitle' => 'Rondes effectuées et progression',
            'period_label' => $periodLabel,
            'columns' => ['Début', 'Fin', 'Agent', 'Site', 'Statut', 'Progression'],
            'rows' => $rows,
            'summary' => [
                ['label' => 'Rondes', 'value' => (string) count($rows)],
            ],
        ];
    }

    private function contrats(?Carbon $from, ?Carbon $to, ?string $periodLabel): array
    {
        $query = Contrat::query()->with('agent')->orderByDesc('date_debut');
        if ($from) {
            $query->whereDate('date_debut', '>=', $from);
        }
        if ($to) {
            $query->whereDate('date_debut', '<=', $to);
        }

        $items = $query->get();
        $rows = $items->map(fn (Contrat $c) => [
            $c->reference ?? '—',
            $c->agent ? trim($c->agent->prenom.' '.$c->agent->nom) : '—',
            $c->agent?->matricule ?? '—',
            $this->enum($c->type),
            $c->date_debut?->format('d/m/Y') ?? '—',
            $c->date_fin?->format('d/m/Y') ?? '—',
            (string) ($c->duree_mois ?? '—'),
            $this->enum($c->statut),
            $c->salaire_net ? number_format((float) $c->salaire_net, 0, ',', ' ') : '—',
        ])->all();

        return [
            'title' => 'Rapport contrats',
            'subtitle' => 'Contrats et rémunération',
            'period_label' => $periodLabel,
            'columns' => ['Réf.', 'Agent', 'Matricule', 'Type', 'Début', 'Fin', 'Durée (m)', 'Statut', 'Net'],
            'rows' => $rows,
            'summary' => [
                ['label' => 'Contrats', 'value' => (string) count($rows)],
                ['label' => 'Actifs', 'value' => (string) $items->where('statut', 'actif')->count()],
            ],
        ];
    }

    private function absences(?Carbon $from, ?Carbon $to, ?string $periodLabel): array
    {
        $query = Absence::query()->with('agent')->orderByDesc('date_debut');
        if ($from) {
            $query->whereDate('date_debut', '>=', $from);
        }
        if ($to) {
            $query->whereDate('date_fin', '<=', $to);
        }

        $items = $query->get();
        $rows = $items->map(fn (Absence $a) => [
            $a->agent ? trim($a->agent->prenom.' '.$a->agent->nom) : '—',
            $a->agent?->matricule ?? '—',
            $this->enum($a->type),
            $a->date_debut?->format('d/m/Y') ?? '—',
            $a->date_fin?->format('d/m/Y') ?? '—',
            $a->motif ?? '—',
            $this->enum($a->statut),
        ])->all();

        return [
            'title' => 'Rapport absences',
            'subtitle' => 'Congés, maladies et permissions',
            'period_label' => $periodLabel,
            'columns' => ['Agent', 'Matricule', 'Type', 'Du', 'Au', 'Motif', 'Statut'],
            'rows' => $rows,
            'summary' => [
                ['label' => 'Absences', 'value' => (string) count($rows)],
                ['label' => 'En attente', 'value' => (string) $items->where('statut', 'en_attente')->count()],
            ],
        ];
    }

    private function paie(?Carbon $from, ?Carbon $to, ?string $periodLabel): array
    {
        $query = BulletinPaie::query()->with(['agent', 'periodePaie'])->latest();
        if ($from) {
            $query->whereHas('periodePaie', fn ($q) => $q->whereDate('date_fin', '>=', $from));
        }
        if ($to) {
            $query->whereHas('periodePaie', fn ($q) => $q->whereDate('date_debut', '<=', $to));
        }

        $items = $query->get();
        $rows = $items->map(fn (BulletinPaie $b) => [
            ($b->periodePaie?->mois ?? '—').'/'.($b->periodePaie?->annee ?? '—'),
            $b->agent ? trim($b->agent->prenom.' '.$b->agent->nom) : '—',
            $b->agent?->matricule ?? '—',
            number_format((float) $b->salaire_brut, 0, ',', ' '),
            number_format((float) $b->salaire_net, 0, ',', ' '),
            $this->enum($b->statut),
        ])->all();

        $masse = $items->sum('salaire_net');

        return [
            'title' => 'Rapport paie',
            'subtitle' => 'Bulletins de salaire',
            'period_label' => $periodLabel,
            'columns' => ['Période', 'Agent', 'Matricule', 'Brut', 'Net', 'Statut'],
            'rows' => $rows,
            'summary' => [
                ['label' => 'Bulletins', 'value' => (string) count($rows)],
                ['label' => 'Masse salariale nette', 'value' => number_format((float) $masse, 0, ',', ' ').' FCFA'],
            ],
        ];
    }

    private function generic(RapportExport $rapport, ?string $periodLabel): array
    {
        return [
            'title' => 'Rapport '.strtoupper((string) $rapport->type),
            'subtitle' => 'Export S.I.S Sécurité-Ops',
            'period_label' => $periodLabel,
            'columns' => ['Information', 'Valeur'],
            'rows' => [
                ['Type', (string) $rapport->type],
                ['Format', (string) $rapport->format],
            ],
            'summary' => [],
        ];
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function periodLabel(?Carbon $from, ?Carbon $to): ?string
    {
        if (! $from && ! $to) {
            return null;
        }

        if ($from && $to) {
            return 'Du '.$from->format('d/m/Y').' au '.$to->format('d/m/Y');
        }

        if ($from) {
            return 'À partir du '.$from->format('d/m/Y');
        }

        return 'Jusqu’au '.$to->format('d/m/Y');
    }

    private function enum(mixed $value): string
    {
        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        return $value === null || $value === '' ? '—' : (string) $value;
    }
}
