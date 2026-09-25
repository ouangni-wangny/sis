<?php

namespace App\Application\Paie;

use App\Models\BulletinPaie;
use App\Models\PeriodePaie;
use App\Support\ModePaiementRules;
use App\Support\PdfBrand;
use App\Support\PdfListExport;
use App\Support\RhAuthorization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class GenererListeBulletinsPdfAction
{
    public function execute(Request $request, PeriodePaie $periode): Response
    {
        $query = self::filteredQuery($request, $periode)
            ->with(['agent.grade'])
            ->orderBy(
                \App\Models\Agent::query()
                    ->select('nom')
                    ->whereColumn('agents.id', 'bulletins_paie.agent_id')
                    ->limit(1)
            )
            ->orderBy(
                \App\Models\Agent::query()
                    ->select('prenom')
                    ->whereColumn('agents.id', 'bulletins_paie.agent_id')
                    ->limit(1)
            );

        $count = PdfListExport::assertQueryCountWithinLimit($query, 'bulletins');
        PdfListExport::prepareRuntime($count);

        $bulletins = $query->get();
        $canSeeSalaire = RhAuthorization::canSeeSalaire($request->user());
        $generatedAt = now()->timezone('Africa/Abidjan')->format('d/m/Y H:i');

        $moisNoms = [
            1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
            5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
            9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre',
        ];
        $periodeLabel = ($moisNoms[(int) $periode->mois] ?? $periode->mois).' '.$periode->annee;

        $filtreParts = [];
        if ($request->filled('grade_id')) {
            $grade = \App\Models\Grade::query()->find($request->string('grade_id'));
            if ($grade) {
                $filtreParts[] = 'Grade : '.$grade->libelle;
            }
        }
        if ($request->filled('ville_id')) {
            $ville = \App\Models\Ville::query()->find($request->string('ville_id'));
            if ($ville) {
                $filtreParts[] = 'Ville : '.$ville->libelle;
            }
        }
        if ($request->filled('statut')) {
            $statutLabels = [
                'brouillon' => 'Brouillon',
                'valide' => 'Validé',
                'paye' => 'Payé',
            ];
            $statut = (string) $request->string('statut');
            $filtreParts[] = 'Statut : '.($statutLabels[$statut] ?? $statut);
        }
        if ($request->filled('mode')) {
            $filtreParts[] = 'Mode : '.ModePaiementRules::label((string) $request->string('mode'));
        }

        $html = view('pdf.bulletins-liste', [
            'brand' => PdfBrand::data(),
            'bulletins' => $bulletins,
            'periode' => $periode,
            'periodeLabel' => $periodeLabel,
            'generatedAt' => $generatedAt,
            'total' => $bulletins->count(),
            'canSeeSalaire' => $canSeeSalaire,
            'filtresLabel' => $filtreParts !== [] ? implode(' · ', $filtreParts) : 'Tous',
        ])->render();

        $filename = 'bulletins-'.$periode->annee.'-'
            .str_pad((string) $periode->mois, 2, '0', STR_PAD_LEFT)
            .'-'.now()->timezone('Africa/Abidjan')->format('Ymd-Hi').'.pdf';

        return PdfListExport::download($html, $filename);
    }

    /**
     * @return Builder<BulletinPaie>|Relation
     */
    public static function filteredQuery(Request $request, PeriodePaie $periode): Builder|Relation
    {
        return $periode->bulletins()
            ->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->string('statut')))
            ->when($request->filled('mode'), fn ($q) => $q->where('mode_paiement', $request->string('mode')))
            ->when($request->boolean('non_payes'), fn ($q) => $q->where('statut', '!=', 'paye'))
            ->when($request->filled('grade_id'), function ($q) use ($request) {
                $q->whereHas('agent', fn ($a) => $a->where('grade_id', $request->string('grade_id')));
            })
            ->when($request->filled('ville_id'), function ($q) use ($request) {
                $q->whereHas('agent', fn ($a) => $a->where('ville_id', $request->string('ville_id')));
            })
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(function ($qq) use ($term) {
                    $qq->where('statut', 'like', $term)
                        ->orWhere('mode_paiement', 'like', $term)
                        ->orWhereHas('agent', function ($agent) use ($term) {
                            $agent->where('nom', 'like', $term)
                                ->orWhere('prenom', 'like', $term)
                                ->orWhere('matricule', 'like', $term)
                                ->orWhere('telephone', 'like', $term)
                                ->orWhereHas('grade', fn ($g) => $g->where('libelle', 'like', $term))
                                ->orWhereHas('villeRef', fn ($v) => $v->where('libelle', 'like', $term));
                        });
                });
            });
    }
}
