<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Commercial\CreateAbonnementsFromProformaAction;
use App\Application\Commercial\CreateProformaFactureAction;
use App\Application\Commercial\GenererFactureDepuisVacationsAction;
use App\Application\Commercial\UpdateProformaFactureAction;
use App\Domain\Shared\Enums\StatutFacture;
use App\Http\Controllers\Controller;
use App\Http\Requests\Facture\GenererFactureRequest;
use App\Http\Requests\Facture\StoreProformaFactureRequest;
use App\Http\Requests\Facture\UpdateFactureStatutRequest;
use App\Http\Resources\FactureResource;
use App\Jobs\GenererFacturePdfJob;
use App\Models\Facture;
use App\Support\ListQuery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FactureController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Facture::class);

        $items = Facture::query()
            ->with(['client', 'abonnement', 'media'])
            ->withSum('paiements as paiements_sum_montant', 'montant')
            ->when($request->client_id, fn ($q, $v) => $q->where('client_id', $v))
            ->when($request->statut, fn ($q, $v) => $q->where('statut', $v))
            ->when($request->filled('periodicite'), fn ($q) => $q->where('periodicite', $request->string('periodicite')))
            ->when($request->boolean('a_recouvrer'), fn ($q) => $this->applyARecouvrerFilter($q))
            ->when(
                $request->filled('statut_paiement') && ! $request->boolean('a_recouvrer'),
                fn ($q) => $this->applyStatutPaiementFilter($q, $request->string('statut_paiement')->toString()),
            )
            ->when(
                $request->boolean('a_recouvrer') && $request->filled('statut_paiement'),
                fn ($q) => $this->applyStatutPaiementRefine($q, $request->string('statut_paiement')->toString()),
            )
            ->when($request->boolean('retard'), fn ($q) => $this->applyRetardFilter($q))
            ->when($request->boolean('echeance_30j'), fn ($q) => $this->applyEcheanceProcheFilter($q, $request->string('statut')->toString()))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q')->toString().'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('numero', 'like', $term)
                        ->orWhere('statut', 'like', $term)
                        ->orWhere('client_nom', 'like', $term)
                        ->orWhereHas('client', function ($c) use ($term) {
                            $c->where('raison_sociale', 'like', $term)
                                ->orWhere('nom_responsable', 'like', $term);
                        });
                });
            });

        if ($request->boolean('a_recouvrer')) {
            $items
                ->orderByRaw('CASE WHEN date_echeance IS NULL THEN 1 ELSE 0 END')
                ->orderBy('date_echeance')
                ->orderBy('numero');
        } else {
            $items->latest();
        }

        return FactureResource::collection(ListQuery::paginateOrAll($items, $request));
    }

    public function show(Facture $facture): FactureResource
    {
        $this->authorize('view', $facture);

        return new FactureResource(
            $facture->load(['client', 'abonnement', 'lignes', 'media'])
                ->loadSum('paiements as paiements_sum_montant', 'montant')
        );
    }

    public function storeProforma(StoreProformaFactureRequest $request, CreateProformaFactureAction $action): FactureResource
    {
        $this->authorize('create', Facture::class);

        return new FactureResource($action->execute($request->validated()));
    }

    public function updateProforma(
        StoreProformaFactureRequest $request,
        Facture $facture,
        UpdateProformaFactureAction $action,
    ): FactureResource {
        $this->authorize('update', $facture);

        return new FactureResource($action->execute($facture, $request->validated()));
    }

    public function generer(GenererFactureRequest $request, GenererFactureDepuisVacationsAction $action): FactureResource
    {
        $this->authorize('create', Facture::class);
        $data = $request->validated();

        return new FactureResource($action->execute($data['client_id'], $data['date_debut'], $data['date_fin']));
    }

    public function updateStatut(
        UpdateFactureStatutRequest $request,
        Facture $facture,
        CreateAbonnementsFromProformaAction $createAbonnements,
    ): FactureResource {
        $this->authorize('update', $facture);
        $statut = $request->validated('statut');
        $facture->update(['statut' => $statut]);

        if ((string) $statut === StatutFacture::Valide->value) {
            $facture = $createAbonnements->execute($facture->fresh());
        }

        return new FactureResource($facture->fresh()->load(['client', 'abonnement', 'lignes', 'media']));
    }

    public function genererPdf(Facture $facture): FactureResource
    {
        $this->authorize('update', $facture);
        GenererFacturePdfJob::dispatchSync($facture->id);

        return new FactureResource($facture->fresh()->load(['media', 'lignes', 'client']));
    }

    public function telechargerPdf(Facture $facture): BinaryFileResponse
    {
        $this->authorize('view', $facture);

        $media = $facture->getFirstMedia('pdf');
        abort_unless($media, 404, 'PDF non généré pour cette facture.');

        // Content-Disposition refuse "/" et "\" (legacy SC/ABJ/N°0001).
        $filename = $this->safePdfFilename($facture->numero);

        return response()->download($media->getPath(), $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    private function safePdfFilename(?string $numero): string
    {
        $base = trim((string) $numero);
        if ($base === '') {
            $base = 'facture';
        }

        $base = str_replace(['/', '\\'], '-', $base);
        $base = preg_replace('/[^\w.\-°]+/u', '_', $base) ?: 'facture';
        $base = trim($base, '._-');

        if ($base === '') {
            $base = 'facture';
        }

        return $base.'.pdf';
    }

    private function applyStatutPaiementFilter($query, string $statutPaiement): void
    {
        $query->where('statut', StatutFacture::Valide);
        $sumSql = '(SELECT COALESCE(SUM(montant), 0) FROM paiements WHERE facture_id = factures.id AND deleted_at IS NULL)';

        match ($statutPaiement) {
            'non_payee' => $query->whereRaw("{$sumSql} <= 0"),
            'partiel' => $query
                ->whereRaw("{$sumSql} > 0")
                ->whereRaw("{$sumSql} < factures.montant_ttc - 0.01"),
            'soldee' => $query->whereRaw("{$sumSql} >= factures.montant_ttc - 1"),
            default => null,
        };
    }

    /** Affiner non_payee / partiel une fois le filtre a_recouvrer déjà appliqué. */
    private function applyStatutPaiementRefine($query, string $statutPaiement): void
    {
        $sumSql = '(SELECT COALESCE(SUM(montant), 0) FROM paiements WHERE facture_id = factures.id AND deleted_at IS NULL)';

        match ($statutPaiement) {
            'non_payee' => $query->whereRaw("{$sumSql} <= 0"),
            'partiel' => $query
                ->whereRaw("{$sumSql} > 0")
                ->whereRaw("{$sumSql} < factures.montant_ttc - 0.01"),
            default => null,
        };
    }

    /** Factures validées non soldées (créances ouvertes). */
    private function applyARecouvrerFilter($query): void
    {
        $query->where('statut', StatutFacture::Valide);
        $sumSql = '(SELECT COALESCE(SUM(montant), 0) FROM paiements WHERE facture_id = factures.id AND deleted_at IS NULL)';
        $query->whereRaw("{$sumSql} < factures.montant_ttc - 0.01");
    }

    private function applyRetardFilter($query): void
    {
        $query
            ->whereNotNull('date_echeance')
            ->whereDate('date_echeance', '<', now()->toDateString());
    }

    private function applyEcheanceProcheFilter($query, string $statutFacture): void
    {
        $limit = now()->addDays(30)->toDateString();
        $query
            ->whereNotNull('date_echeance')
            ->whereDate('date_echeance', '<=', $limit);

        if ($statutFacture === StatutFacture::Valide->value) {
            $sumSql = '(SELECT COALESCE(SUM(montant), 0) FROM paiements WHERE facture_id = factures.id AND deleted_at IS NULL)';
            $query->whereRaw("{$sumSql} < factures.montant_ttc - 0.01");
        }
    }
}