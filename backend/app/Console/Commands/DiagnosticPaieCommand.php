<?php

namespace App\Console\Commands;

use App\Models\Agent;
use App\Models\BulletinPaie;
use App\Models\PeriodePaie;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Diagnostic paie en lecture seule : pourquoi un agent est absent de la
 * liste paie d’une période, ou pourquoi son salaire ne peut pas être saisi.
 *
 *   php artisan paie:diagnostic 9 2026 "Kante Yacouba" "Diakite Bamory"
 */
class DiagnosticPaieCommand extends Command
{
    protected $signature = 'paie:diagnostic
                            {mois : Mois de la période (1-12)}
                            {annee : Année de la période}
                            {noms* : Noms des agents (prénom et nom, dans n’importe quel ordre)}';

    protected $description = 'Explique, sans rien modifier, la situation paie d’agents pour une période';

    public function handle(): int
    {
        $periode = PeriodePaie::query()
            ->where('mois', (int) $this->argument('mois'))
            ->where('annee', (int) $this->argument('annee'))
            ->first();

        if (! $periode) {
            $this->error('Aucune période de paie pour ce mois.');

            return self::FAILURE;
        }

        $debut = $periode->date_debut->toDateString();
        $fin = $periode->date_fin->toDateString();
        $this->info("Période {$periode->mois}/{$periode->annee} ({$debut} → {$fin}) — statut {$periode->statut->value}");

        foreach ($this->argument('noms') as $nom) {
            $this->newLine();
            $this->line("<options=bold>■ {$nom}</>");

            $agents = $this->trouver($nom);
            if ($agents->isEmpty()) {
                $this->warn('  Agent introuvable (vérifier l’orthographe).');

                continue;
            }

            foreach ($agents as $agent) {
                $this->line(sprintf(
                    '  Agent %s %s (%s) — statut %s',
                    $agent->prenom,
                    $agent->nom,
                    $agent->matricule,
                    $agent->statut?->value ?? '—',
                ));

                $contrats = $agent->contrats()->orderByDesc('date_debut')->get();
                if ($contrats->isEmpty()) {
                    $this->warn('  → Aucun contrat : pas de bulletin possible. Créer le contrat (RH › Contrats).');
                }
                foreach ($contrats as $c) {
                    $couvre = $c->date_debut->toDateString() <= $fin
                        && ($c->date_fin === null || $c->date_fin->toDateString() >= $debut);
                    $this->line(sprintf(
                        '    Contrat %s %s : %s → %s, statut %s, salaire %s%s',
                        $c->reference ?? '',
                        $c->type instanceof \BackedEnum ? $c->type->value : (string) $c->type,
                        $c->date_debut->toDateString(),
                        $c->date_fin?->toDateString() ?? 'sans fin',
                        $c->statut,
                        $c->salaire_base ?? $c->salaire_brut ?? $c->salaire ?? '—',
                        $couvre ? '  [couvre la période]' : '',
                    ));
                }

                $bulletin = BulletinPaie::query()
                    ->where('periode_paie_id', $periode->id)
                    ->where('agent_id', $agent->id)
                    ->first();

                if (! $bulletin) {
                    $this->warn('  → Pas de bulletin dans cette période : relancer « Générer les bulletins ».');

                    continue;
                }

                $details = is_array($bulletin->details) ? $bulletin->details : [];
                $this->line(sprintf(
                    '  Bulletin : statut %s, net %s, salaire perçu %s, motif %s',
                    $bulletin->statut->value,
                    $bulletin->salaire_net,
                    array_key_exists('salaire_percu', $details) ? 'saisi ('.$details['salaire_percu'].')' : 'non saisi',
                    $details['motif'] ?? 'periode',
                ));
                if ($bulletin->statut->value === 'paye') {
                    $this->warn('  → Bulletin déjà payé : la saisie du salaire est verrouillée.');
                }
                if ($periode->statut->value === 'cloturee') {
                    $this->warn('  → Période clôturée.');
                }
            }
        }

        return self::SUCCESS;
    }

    /** Recherche tolérante : tous les mots du nom doivent apparaître dans prénom + nom. */
    private function trouver(string $nom)
    {
        $mots = collect(preg_split('/\s+/', Str::of($nom)->ascii()->lower()->toString()))->filter();

        return Agent::query()
            ->get(['id', 'prenom', 'nom', 'matricule', 'statut'])
            ->filter(function (Agent $a) use ($mots) {
                $complet = Str::of($a->prenom.' '.$a->nom)->ascii()->lower()->toString();

                return $mots->every(fn ($m) => str_contains($complet, $m));
            })
            ->values();
    }
}
