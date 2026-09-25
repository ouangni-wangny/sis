<?php

namespace App\Application\Contrat;

use App\Models\Contrat;
use App\Support\PdfBrand;
use App\Support\PdfListExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class GenererListeContratsPdfAction
{
    public function execute(Request $request): Response
    {
        $query = self::filteredQuery($request)
            ->with(['agent'])
            ->orderByDesc('date_debut')
            ->orderBy('reference');

        $count = PdfListExport::assertQueryCountWithinLimit($query, 'contrats');
        PdfListExport::prepareRuntime($count);

        $contrats = $query->get();
        $generatedAt = now()->timezone('Africa/Abidjan')->format('d/m/Y H:i');

        $html = view('pdf.contrats-liste', [
            'brand' => PdfBrand::data(),
            'contrats' => $contrats,
            'generatedAt' => $generatedAt,
            'total' => $contrats->count(),
        ])->render();

        return PdfListExport::download(
            $html,
            'contrats-'.now()->timezone('Africa/Abidjan')->format('Ymd-Hi').'.pdf',
        );
    }

    /**
     * @return Builder<Contrat>
     */
    public static function filteredQuery(Request $request): Builder
    {
        return Contrat::query()
            ->when($request->agent_id, fn ($q, $v) => $q->where('agent_id', $v))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->string('statut')))
            ->when($request->boolean('valide'), fn ($q) => $q->valide())
            ->when($request->boolean('surveillance'), fn ($q) => $q->aSurveiller())
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(function ($qq) use ($term) {
                    $qq->where('reference', 'like', $term)
                        ->orWhere('type', 'like', $term)
                        ->orWhere('statut', 'like', $term)
                        ->orWhereHas('agent', function ($agent) use ($term) {
                            $agent->where('nom', 'like', $term)
                                ->orWhere('prenom', 'like', $term)
                                ->orWhere('matricule', 'like', $term);
                        });
                });
            });
    }
}
