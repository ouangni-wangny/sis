<?php

namespace App\Application\Referentiel;

use App\Models\Client;
use App\Support\PdfBrand;
use App\Support\PdfListExport;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class GenererListeClientsPdfAction
{
    public function execute(Request $request): Response
    {
        $query = Client::query()
            ->withCount('sites')
            ->when($request->filled('q'), fn ($q) => $q->where('raison_sociale', 'like', '%'.$request->string('q')->toString().'%'))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->string('statut')))
            ->orderBy('raison_sociale');

        $count = PdfListExport::assertQueryCountWithinLimit($query, 'clients');
        PdfListExport::prepareRuntime($count);

        $clients = $query->get();
        $generatedAt = now()->timezone('Africa/Abidjan')->format('d/m/Y H:i');

        $html = view('pdf.clients-liste', [
            'brand' => PdfBrand::data(),
            'clients' => $clients,
            'generatedAt' => $generatedAt,
            'total' => $clients->count(),
        ])->render();

        return PdfListExport::download(
            $html,
            'clients-'.now()->timezone('Africa/Abidjan')->format('Ymd-Hi').'.pdf',
        );
    }
}
