<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>FACTURE PRO FORMA {{ $facture->numero }}</title>
    @include('pdf.partials.styles')
    <style>
        /* Ajustements facture — grille commerciale */
        @page { margin: 18mm 14mm 20mm 14mm; }

        table.sis-invoice {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-bottom: 12px;
            border: 1px solid #d2d6cc;
        }

        table.sis-invoice thead th {
            background: #2f3a24;
            color: #fff;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            padding: 8px 6px;
            text-align: center;
            border: none;
        }

        table.sis-invoice tbody td {
            border-bottom: 1px solid #d2d6cc;
            border-right: 1px solid #e8eae4;
            padding: 6px 6px;
            font-size: 9.5px;
            vertical-align: middle;
        }

        table.sis-invoice tbody td:last-child { border-right: none; }

        table.sis-invoice tbody tr:nth-child(even) td { background: #f4f5f2; }

        table.sis-invoice .empty-row td {
            height: 16px;
            padding: 0;
            line-height: 16px;
            border-bottom: 1px solid #e8eae4;
        }

        table.sis-invoice .total-row td {
            background: #f4f5f2;
            font-size: 10px;
            padding: 7px 6px;
            border-bottom: 1px solid #d2d6cc;
        }

        table.sis-invoice .total-row.pay td {
            background: #2f3a24;
            color: #fff;
            font-weight: bold;
            border-bottom: none;
            padding: 9px 6px;
            font-size: 11px;
        }

        .col-code { width: 14%; }
        .col-des  { width: 42%; }
        .col-qty  { width: 8%; text-align: center; }
        .col-pu   { width: 18%; text-align: right; white-space: nowrap; }
        .col-mt   { width: 18%; text-align: right; white-space: nowrap; }
    </style>
</head>
<body>
@php
    $brand = \App\Support\PdfBrand::data();
    $fmt = static fn ($n) => number_format((float) $n, 0, ',', ' ');
    $fmtQty = static function ($n) {
        $v = number_format((float) $n, 2, ',', ' ');

        return rtrim(rtrim($v, '0'), ',');
    };

    $taux = (float) ($facture->taux_tva ?? 18);
    $tauxLabel = rtrim(rtrim(number_format($taux, 2, ',', ' '), '0'), ',');

    $clientNom = $facture->client_nom ?: ($facture->client?->raison_sociale ?? '—');
    $clientAdresse = $facture->client_adresse ?: ($facture->client?->adresse ?? null);
    $clientTel = $facture->client_telephone ?: ($facture->client?->telephone ?? null);
    $clientEmail = $facture->client_email ?: ($facture->client?->email ?? null);

    $lieu = $facture->lieu_emission ?: 'Abidjan';
    $dateFr = $facture->date_emission
        ? $facture->date_emission->locale('fr')->translatedFormat('d F Y')
        : '—';
    $dateFr = preg_replace_callback(
        '/\b([a-zéûôà]+)\b/u',
        static fn ($m) => mb_convert_case($m[1], MB_CASE_TITLE, 'UTF-8'),
        $dateFr
    );

    $affaire = $facture->affaire_suivie_par
        ?: (string) config('sis.commercial.affaire_suivie_par', 'SERVICE COMMERCIAL');
    $telCommercial = $facture->telephone_commercial
        ?: (string) config('sis.commercial.telephone', '22 50 31 77');
    $apporteur = (string) config('sis.commercial.apporteur', '');

    $nbText = $facture->notes
        ?: ("L'abonnement mensuel sera de ".$fmt($facture->montant_ttc).' FCFA TTC');

    $lettres = $facture->montant_ttc_lettres
        ?: \App\Domain\Shared\Support\MontantEnLettres::execute($facture->montant_ttc);

    $lignes = $facture->lignes ?? collect();
    $emptyRows = max(4, 10 - $lignes->count());

    $statutFacture = $facture->statut instanceof \BackedEnum
        ? $facture->statut->value
        : (string) ($facture->statut ?? '');
@endphp

@include('pdf.partials.header', [
    'brand' => $brand,
    'docEyebrow' => 'Commercial',
    'docTitle' => 'Facture pro forma',
    'docSubtitle' => $facture->numero.' · '.$lieu.', le '.$dateFr,
])

<table style="width:100%; border-collapse:collapse; margin-bottom:14px;">
    <tr>
        <td style="width:50%; padding-right:8px; vertical-align:top;">
            <div class="sis-panel">
                <p class="sis-panel-label">Émetteur</p>
                <p style="font-weight:bold; font-size:11px;">{{ $brand['company_name'] }}</p>
                <p style="margin-top:4px;"><span class="lbl">Affaire suivie par</span><br>{{ $affaire }}</p>
                @if($telCommercial !== '')
                    <p style="margin-top:4px;"><span class="lbl">Téléphone</span><br>{{ $telCommercial }}</p>
                @endif
                @if($apporteur !== '')
                    <p style="margin-top:4px;"><span class="lbl">Apporteur</span><br>{{ $apporteur }}</p>
                @endif
            </div>
        </td>
        <td style="width:50%; padding-left:8px; vertical-align:top;">
            <div class="sis-panel">
                <p class="sis-panel-label">Doit — Client</p>
                <p style="font-weight:bold; font-size:11px;">{{ $clientNom }}</p>
                @if($clientAdresse)
                    <p style="margin-top:4px;">{{ $clientAdresse }}</p>
                @else
                    <p style="margin-top:4px;" class="upper">{{ $lieu }}</p>
                @endif
                @if($clientTel)
                    <p style="margin-top:4px;"><span class="lbl">Tél</span><br>{{ $clientTel }}</p>
                @endif
                @if($clientEmail)
                    <p style="margin-top:4px;"><span class="lbl">Email</span><br>{{ $clientEmail }}</p>
                @endif
            </div>
        </td>
    </tr>
</table>

<table class="sis-invoice">
    <thead>
        <tr>
            <th class="col-code">Code</th>
            <th class="col-des" style="text-align:left;">Désignation</th>
            <th class="col-qty">Qté</th>
            <th class="col-pu">P.U. HT</th>
            <th class="col-mt">Montant HT</th>
        </tr>
    </thead>
    <tbody>
        @forelse($lignes as $ligne)
            <tr>
                <td class="col-code">{{ $ligne->code_article ?: '—' }}</td>
                <td class="col-des">{{ $ligne->description }}</td>
                <td class="col-qty">{{ $fmtQty($ligne->quantite) }}</td>
                <td class="col-pu">{{ $fmt($ligne->prix_unitaire) }}</td>
                <td class="col-mt">{{ $fmt($ligne->montant) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="5" class="center muted">Aucune prestation</td>
            </tr>
        @endforelse

        @for($i = 0; $i < $emptyRows; $i++)
            <tr class="empty-row">
                <td class="col-code">&nbsp;</td>
                <td class="col-des">&nbsp;</td>
                <td class="col-qty">&nbsp;</td>
                <td class="col-pu">&nbsp;</td>
                <td class="col-mt">&nbsp;</td>
            </tr>
        @endfor

        <tr class="total-row">
            <td colspan="3">&nbsp;</td>
            <td class="right bold">Montant total HT</td>
            <td class="col-mt bold">{{ $fmt($facture->montant_ht) }}</td>
        </tr>
        <tr class="total-row">
            <td colspan="3">&nbsp;</td>
            <td class="right bold">TVA ({{ $tauxLabel }}%)</td>
            <td class="col-mt bold">{{ $fmt($facture->montant_tva) }}</td>
        </tr>
        <tr class="total-row pay">
            <td colspan="3">&nbsp;</td>
            <td class="right">Total à payer</td>
            <td class="col-mt">{{ $fmt($facture->montant_ttc) }} FCFA</td>
        </tr>
    </tbody>
</table>

<div class="sis-note">
    Arrêté le présent devis à la somme de : <strong>{{ $lettres }}</strong>
</div>

<p style="margin:0 0 14px 0; font-size:10px;">
    <span class="sis-badge">NB</span>
    <span style="margin-left:6px;">{{ $nbText }}</span>
</p>

<table style="width:100%; border-collapse:collapse; margin-top:6px;">
    <tr>
        <td style="width:54%; padding-right:8px; vertical-align:top;">
            <div class="sis-panel">
                <p class="sis-panel-label">Conditions de mise en place</p>
                @if($facture->periodicite)
                    <p>Facturation :
                        <strong>{{ ucfirst((string) ($facture->periodicite->value ?? $facture->periodicite)) }}</strong>
                    </p>
                @endif
                @if($facture->periode_debut && $facture->periode_fin)
                    <p>Période facturée :
                        <strong>
                            {{ $facture->periode_debut->format('d/m/Y') }}
                            —
                            {{ $facture->periode_fin->format('d/m/Y') }}
                        </strong>
                    </p>
                @endif
                @if($facture->conditions_paiement)
                    <p>{{ $facture->conditions_paiement }}</p>
                @endif
                @if($facture->delai_validite)
                    <p>Délai de validité du devis : {{ $facture->delai_validite }}</p>
                @endif
                @if($facture->duree_contrat_min)
                    <p>{{ $facture->duree_contrat_min }}</p>
                @endif
            </div>
        </td>
        <td style="width:46%; padding-left:8px; vertical-align:top;">
            <div class="sis-sign-box">
                <p class="sis-sign-label">
                    {{ $facture->signataire_fonction ?: 'Signature &amp; cachet' }}
                </p>
                <div style="height:48px;"></div>
                @if($facture->signataire_nom)
                    <p class="bold" style="margin:0;">{{ $facture->signataire_nom }}</p>
                @else
                    <p class="faint" style="margin:0; font-size:8.5px;">{{ $brand['company_name'] }}</p>
                @endif
            </div>
        </td>
    </tr>
</table>

@include('pdf.partials.footer', [
    'brand' => $brand,
    'footerRight' => ($statutFacture ? strtoupper($statutFacture).' · ' : '').$facture->numero.'<br>Document commercial SIS',
])
</body>
</html>
