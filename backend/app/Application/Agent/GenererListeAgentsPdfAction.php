<?php

namespace App\Application\Agent;

use App\Domain\Shared\Enums\TypeAgent;
use App\Models\Agent;
use App\Support\PdfBrand;
use App\Support\PdfListExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class GenererListeAgentsPdfAction
{
    public function execute(Request $request): Response
    {
        $query = self::filteredQuery($request)
            ->with(['grade', 'villeRef'])
            ->orderBy('nom')
            ->orderBy('prenom');

        $count = PdfListExport::assertQueryCountWithinLimit($query, 'fiches personnel');
        PdfListExport::prepareRuntime($count);

        $agents = $query->get();
        $generatedAt = now()->timezone('Africa/Abidjan')->format('d/m/Y H:i');

        $html = view('pdf.agents-liste', [
            'brand' => PdfBrand::data(),
            'agents' => $agents,
            'generatedAt' => $generatedAt,
            'total' => $agents->count(),
        ])->render();

        return PdfListExport::download(
            $html,
            'personnel-'.now()->timezone('Africa/Abidjan')->format('Ymd-Hi').'.pdf',
        );
    }

    /**
     * @return Builder<Agent>
     */
    public static function filteredQuery(Request $request): Builder
    {
        return Agent::query()
            ->when(
                $request->user()?->hasRole('operation'),
                fn ($q) => $q->where('type', '!=', TypeAgent::Administration)
            )
            ->when($request->type, fn ($q, $v) => $q->where('type', $v))
            ->when($request->statut, fn ($q, $v) => $q->where('statut', $v))
            ->when($request->grade_id, fn ($q, $v) => $q->where('grade_id', $v))
            ->when($request->ville_id, fn ($q, $v) => $q->where('ville_id', $v))
            ->when($request->has('pool_siege'), fn ($q) => $q->where('pool_siege', $request->boolean('pool_siege')))
            ->when($request->boolean('contrat_valide'), fn ($q) => $q->avecContratValide())
            ->when($request->q, fn ($q, $v) => $q->where(fn ($qq) => $qq
                ->where('nom', 'like', "%{$v}%")
                ->orWhere('prenom', 'like', "%{$v}%")
                ->orWhere('matricule', 'like', "%{$v}%")));
    }
}
