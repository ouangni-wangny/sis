<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Bulletin de paie</title>
    @include('pdf.partials.styles')
    <style>
        @page { margin: 12mm 11mm 15mm 11mm; }
        body { font-size: 9.5px; }

        .bp-banner {
            width: 100%;
            border-collapse: collapse;
            margin: 0 0 12px 0;
            background: #2f3a24;
            color: #fff;
        }
        .bp-banner td { padding: 12px 14px; vertical-align: middle; }
        .bp-banner .k {
            display: block;
            font-size: 7.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: #c5d0b8;
            margin-bottom: 3px;
        }
        .bp-banner .v {
            font-size: 20px;
            font-weight: bold;
            letter-spacing: 0.4px;
            line-height: 1.1;
        }
        .bp-banner .side {
            text-align: right;
            font-size: 9px;
            color: #d5ddd0;
            line-height: 1.55;
        }
        .bp-badge {
            display: inline-block;
            margin-top: 4px;
            padding: 2px 8px;
            background: #4a5c38;
            color: #fff;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }
        .bp-badge.paye { background: #311f38; }

        .bp-meta {
            width: 100%;
            border-collapse: collapse;
            margin: 0 0 12px 0;
        }
        .bp-meta td {
            width: 33.33%;
            border: 1px solid #d2d6cc;
            background: #f4f5f2;
            padding: 8px 10px;
            vertical-align: top;
        }
        .bp-meta .k {
            display: block;
            font-size: 7px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #7a8470;
            margin-bottom: 3px;
        }
        .bp-meta .v {
            font-size: 12px;
            font-weight: bold;
            color: #2f3a24;
        }
        .bp-meta .v.deduit { color: #311f38; }

        .bp-id {
            width: 100%;
            border-collapse: collapse;
            margin: 0 0 12px 0;
        }
        .bp-id > tbody > tr > td {
            width: 50%;
            vertical-align: top;
            padding: 0;
        }
        .bp-id .pad-r { padding-right: 5px; }
        .bp-id .pad-l { padding-left: 5px; }
        .bp-card {
            border: 1px solid #d2d6cc;
            background: #fff;
            padding: 9px 11px;
            min-height: 88px;
        }
        .bp-card-title {
            margin: 0 0 7px 0;
            padding-bottom: 5px;
            border-bottom: 1.5px solid #2f3a24;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1.1px;
            color: #2f3a24;
        }
        .bp-name {
            margin: 0 0 5px 0;
            font-size: 12px;
            font-weight: bold;
            color: #141a10;
        }
        .bp-row {
            margin: 0 0 2px 0;
            font-size: 9px;
            color: #141a10;
            line-height: 1.4;
        }
        .bp-row .lbl {
            color: #7a8470;
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        table.bp-lines {
            width: 100%;
            border-collapse: collapse;
            margin: 0 0 10px 0;
            border: 1px solid #d2d6cc;
        }
        table.bp-lines thead th {
            background: #2f3a24;
            color: #fff;
            font-size: 7.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            padding: 7px 6px;
            border: none;
            text-align: left;
        }
        table.bp-lines thead th.right { text-align: right; }
        table.bp-lines thead th.center { text-align: center; }
        table.bp-lines tbody td {
            padding: 5px 6px;
            border-bottom: 1px solid #e8eae4;
            border-right: 1px solid #e8eae4;
            font-size: 9px;
            vertical-align: middle;
            color: #141a10;
        }
        table.bp-lines tbody td:last-child { border-right: none; }
        table.bp-lines tbody tr:nth-child(even) td { background: #f4f5f2; }
        table.bp-lines tbody tr.section td {
            background: #e8eae4;
            font-weight: bold;
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.7px;
            color: #2f3a24;
            padding: 5px 6px;
        }
        table.bp-lines tbody tr.subtotal td {
            background: #f4f5f2;
            font-weight: bold;
            border-top: 1px solid #d2d6cc;
            border-bottom: 1px solid #d2d6cc;
        }
        table.bp-lines tbody tr.neg td.amt { color: #311f38; }
        table.bp-lines tbody tr.net td {
            background: #2f3a24;
            color: #fff;
            font-weight: bold;
            font-size: 10.5px;
            border: none;
            padding: 8px 6px;
        }
        table.bp-lines .code {
            font-size: 7.5px;
            color: #7a8470;
            font-family: DejaVu Sans Mono, monospace;
        }
        table.bp-lines tbody tr.net .code { color: #c5d0b8; }

        .bp-pay {
            width: 100%;
            border-collapse: collapse;
            margin: 0 0 10px 0;
            border: 1px solid #d2d6cc;
        }
        .bp-pay td {
            padding: 8px 10px;
            vertical-align: top;
            background: #f4f5f2;
            border-right: 1px solid #e8eae4;
            font-size: 9px;
        }
        .bp-pay td:last-child { border-right: none; }
        .bp-pay .k {
            display: block;
            font-size: 7px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.9px;
            color: #7a8470;
            margin-bottom: 2px;
        }
        .bp-pay .v { font-weight: bold; color: #141a10; font-size: 10px; }

        .bp-legal {
            margin: 8px 0 10px 0;
            padding: 7px 10px;
            border-left: 3px solid #311f38;
            background: #f4f5f2;
            font-size: 8px;
            color: #4a5340;
            line-height: 1.45;
        }

        .bp-sign {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
        }
        .bp-sign td { width: 50%; vertical-align: top; padding: 0; }
        .bp-sign .box {
            border: 1px solid #d2d6cc;
            background: #fff;
            padding: 10px 12px;
            min-height: 72px;
        }
        .bp-sign .pad-r { padding-right: 5px; }
        .bp-sign .pad-l { padding-left: 5px; }
        .bp-sign .ttl {
            margin: 0 0 4px 0;
            font-size: 7.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #2f3a24;
        }
        .bp-sign .hint {
            margin: 28px 0 0 0;
            font-size: 7.5px;
            color: #7a8470;
        }

        .sis-logo { width: 46px; height: 46px; }
        .sis-doc-title { font-size: 13px; }
        .sis-rule { margin: 8px 0 2px 0; }
        .sis-rule-accent { margin: 0 0 10px 0; }
        .sis-section {
            margin: 4px 0 6px 0;
            font-size: 8.5px;
            padding-bottom: 4px;
        }
    </style>
</head>
<body>
@php
    $brand = \App\Support\PdfBrand::data();
    $fmt = static fn ($n) => number_format((float) $n, 0, ',', ' ').' F CFA';
    $fmtNum = static fn ($n) => number_format((float) $n, 0, ',', ' ');
    $pct = static fn ($t) => number_format((float) $t * 100, 1, ',', ' ').' %';

    $moisNoms = [
        1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
        5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
        9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre',
    ];
    $mois = (int) ($bulletin->periodePaie->mois ?? 0);
    $annee = (int) ($bulletin->periodePaie->annee ?? 0);
    $periodeLabel = ($moisNoms[$mois] ?? $mois).' '.$annee;

    $typeContrat = $bulletin->contrat?->type;
    $typeLabel = $typeContrat instanceof \BackedEnum
        ? strtoupper($typeContrat->value)
        : ($typeContrat ? strtoupper((string) $typeContrat) : null);

    $statut = $bulletin->statut instanceof \BackedEnum
        ? $bulletin->statut->value
        : (string) ($bulletin->statut ?? '');
    $statutLabel = match ($statut) {
        'paye' => 'Payé',
        'valide' => 'Validé',
        'brouillon' => 'Brouillon',
        default => $statut !== '' ? ucfirst($statut) : '—',
    };

    $details = is_array($bulletin->details) ? $bulletin->details : [];
    $rubriques = is_array($details['rubriques'] ?? null) ? $details['rubriques'] : null;

    // Fallback : rubriques depuis le contrat si pas figées à la génération
    if ($rubriques === null && $bulletin->contrat) {
        $c = $bulletin->contrat;
        $rubriques = [
            'salaire_base' => (float) ($c->salaire_base ?? $c->salaire_brut ?? $c->salaire ?? 0),
            'indemnite_fonction' => (float) ($c->indemnite_fonction ?? 0),
            'prime_responsabilite' => (float) ($c->prime_responsabilite ?? 0),
            'prime_transport' => (float) ($c->prime_transport ?? 0),
            'prime_entretien_tenue' => (float) ($c->prime_entretien_tenue ?? 0),
            'sursalaire' => (float) ($c->sursalaire ?? 0),
        ];
    }
    $rubriques = $rubriques ?? [];

    $gainLines = [
        ['code' => '100', 'label' => 'Salaire de base', 'key' => 'salaire_base'],
        ['code' => '110', 'label' => 'Indemnité de fonction', 'key' => 'indemnite_fonction'],
        ['code' => '120', 'label' => 'Prime de responsabilité', 'key' => 'prime_responsabilite'],
        ['code' => '130', 'label' => 'Prime de transport', 'key' => 'prime_transport'],
        ['code' => '140', 'label' => 'Prime entretien / tenue', 'key' => 'prime_entretien_tenue'],
        ['code' => '150', 'label' => 'Sursalaire', 'key' => 'sursalaire'],
    ];

    $brut = (float) $bulletin->salaire_brut;
    $cnps = (float) $bulletin->retenue_cnps;
    $igr = (float) $bulletin->montant_igr;
    $netCalcule = array_key_exists('salaire_calcule_net', $details)
        ? (float) $details['salaire_calcule_net']
        : round($brut - $cnps - $igr, 2);
    $netPercu = array_key_exists('salaire_percu', $details)
        ? (float) $details['salaire_percu']
        : (float) $bulletin->salaire_net;
    $totalRetenues = round($cnps + $igr, 2);
    $partsIgr = $details['parts_igr'] ?? $bulletin->contrat?->parts_igr;
    $cnpsTaux = (float) ($details['cnps_taux'] ?? \App\Domain\Paie\CalculRemunerationCi::CNPS_SALARIE_TAUX);

    $modeLabel = $bulletin->mode_paiement
        ? \App\Support\ModePaiementRules::label((string) $bulletin->mode_paiement)
        : null;

    $refDoc = strtoupper(substr((string) $bulletin->id, 0, 8));
@endphp

@include('pdf.partials.header', [
    'brand' => $brand,
    'docEyebrow' => 'Ressources humaines · Document confidentiel',
    'docTitle' => 'Bulletin de paie',
    'docSubtitle' => 'Période '.$periodeLabel.' · N° '.$refDoc.' · Émis le '.$generatedAt,
])

{{-- Bandeau net à payer --}}
<table class="bp-banner">
    <tr>
        <td style="width: 58%;">
            <span class="k">Net à payer</span>
            <span class="v">{{ $fmt($netPercu) }}</span>
        </td>
        <td class="side" style="width: 42%;">
            Période <strong style="color:#fff;">{{ $periodeLabel }}</strong><br>
            @if($bulletin->paye_le)
                Payé le {{ $bulletin->paye_le->format('d/m/Y') }}
            @else
                En attente de règlement
            @endif
            <br>
            <span class="bp-badge {{ $statut === 'paye' ? 'paye' : '' }}">{{ $statutLabel }}</span>
        </td>
    </tr>
</table>

{{-- Synthèse KPI --}}
<table class="bp-meta">
    <tr>
        <td>
            <span class="k">Salaire brut</span>
            <span class="v">{{ $fmt($brut) }}</span>
        </td>
        <td>
            <span class="k">Total retenues</span>
            <span class="v deduit">− {{ $fmt($totalRetenues) }}</span>
        </td>
        <td>
            <span class="k">Net calculé</span>
            <span class="v">{{ $fmt($netCalcule) }}</span>
        </td>
    </tr>
</table>

{{-- Identité --}}
<table class="bp-id">
    <tr>
        <td class="pad-r">
            <div class="bp-card">
                <p class="bp-card-title">Salarié</p>
                <p class="bp-name">{{ $bulletin->agent->prenom }} {{ $bulletin->agent->nom }}</p>
                <p class="bp-row"><span class="lbl">Matricule</span> · {{ $bulletin->agent->matricule ?: '—' }}</p>
                @if($bulletin->agent->grade)
                    <p class="bp-row"><span class="lbl">Grade</span> · {{ $bulletin->agent->grade->libelle }}</p>
                @endif
                @if($bulletin->agent->cnps)
                    <p class="bp-row"><span class="lbl">N° CNPS</span> · {{ $bulletin->agent->cnps }}</p>
                @endif
                @if($bulletin->agent->date_embauche)
                    <p class="bp-row"><span class="lbl">Embauche</span> · {{ $bulletin->agent->date_embauche->format('d/m/Y') }}</p>
                @endif
                @if($bulletin->agent->situation_matrimoniale || $bulletin->agent->nombre_enfants)
                    @php
                        $sit = $bulletin->agent->situation_matrimoniale;
                        $sitLabel = match ($sit instanceof \BackedEnum ? $sit->value : (string) $sit) {
                            'marie' => 'Marié(e)',
                            'celibataire' => 'Célibataire',
                            'divorce' => 'Divorcé(e)',
                            'veuf' => 'Veuf(ve)',
                            default => $sit instanceof \BackedEnum ? $sit->value : ($sit ?: '—'),
                        };
                    @endphp
                    <p class="bp-row">
                        <span class="lbl">Situation</span> · {{ $sitLabel }}
                        @if($bulletin->agent->nombre_enfants !== null)
                            · {{ (int) $bulletin->agent->nombre_enfants }} enfant(s)
                        @endif
                    </p>
                @endif
            </div>
        </td>
        <td class="pad-l">
            <div class="bp-card">
                <p class="bp-card-title">Employeur &amp; contrat</p>
                <p class="bp-name" style="font-size:11px;">{{ $brand['company_name'] }}</p>
                @if($bulletin->contrat)
                    <p class="bp-row">
                        <span class="lbl">Contrat</span> · {{ $bulletin->contrat->reference ?: '—' }}
                        @if($typeLabel) · {{ $typeLabel }} @endif
                    </p>
                    @if($bulletin->contrat->date_debut)
                        <p class="bp-row">
                            <span class="lbl">Validité</span> ·
                            {{ $bulletin->contrat->date_debut->format('d/m/Y') }}
                            @if($bulletin->contrat->date_fin)
                                → {{ $bulletin->contrat->date_fin->format('d/m/Y') }}
                            @else
                                → CDI
                            @endif
                        </p>
                    @endif
                @else
                    <p class="bp-row muted">Aucun contrat lié</p>
                @endif
                <p class="bp-row"><span class="lbl">Période de paie</span> · {{ $periodeLabel }}</p>
                @if($partsIgr)
                    <p class="bp-row"><span class="lbl">Parts fiscales</span> · {{ $partsIgr }}</p>
                @endif
            </div>
        </td>
    </tr>
</table>

<p class="sis-section">Détail de la rémunération</p>

<table class="bp-lines">
    <thead>
        <tr>
            <th style="width:10%;">Code</th>
            <th style="width:44%;">Libellé</th>
            <th class="center" style="width:12%;">Base / Taux</th>
            <th class="right" style="width:17%;">Gains</th>
            <th class="right" style="width:17%;">Retenues</th>
        </tr>
    </thead>
    <tbody>
        <tr class="section">
            <td colspan="5">Éléments de rémunération (gains)</td>
        </tr>
        @php $hasGain = false; @endphp
        @foreach($gainLines as $line)
            @php $val = (float) ($rubriques[$line['key']] ?? 0); @endphp
            @if($val > 0)
                @php $hasGain = true; @endphp
                <tr>
                    <td><span class="code">{{ $line['code'] }}</span></td>
                    <td>{{ $line['label'] }}</td>
                    <td class="center faint">—</td>
                    <td class="right">{{ $fmtNum($val) }}</td>
                    <td class="right faint">—</td>
                </tr>
            @endif
        @endforeach
        @unless($hasGain)
            <tr>
                <td><span class="code">100</span></td>
                <td>Salaire brut</td>
                <td class="center faint">—</td>
                <td class="right">{{ $fmtNum($brut) }}</td>
                <td class="right faint">—</td>
            </tr>
        @endunless
        <tr class="subtotal">
            <td></td>
            <td>Total brut</td>
            <td></td>
            <td class="right">{{ $fmtNum($brut) }}</td>
            <td></td>
        </tr>

        <tr class="section">
            <td colspan="5">Cotisations &amp; impôts (retenues salariales)</td>
        </tr>
        <tr class="neg">
            <td><span class="code">210</span></td>
            <td>Retenue CNPS (part salarié)</td>
            <td class="center">{{ $pct($cnpsTaux) }}</td>
            <td class="right faint">—</td>
            <td class="right amt">{{ $fmtNum($cnps) }}</td>
        </tr>
        <tr class="neg">
            <td><span class="code">220</span></td>
            <td>Impôt sur les traitements et salaires (IGR / ITS)
                @if($partsIgr)
                    <span class="faint"> · {{ $partsIgr }} part(s)</span>
                @endif
            </td>
            <td class="center faint">barème</td>
            <td class="right faint">—</td>
            <td class="right amt">{{ $fmtNum($igr) }}</td>
        </tr>
        <tr class="subtotal">
            <td></td>
            <td>Total retenues</td>
            <td></td>
            <td></td>
            <td class="right">{{ $fmtNum($totalRetenues) }}</td>
        </tr>

        <tr class="net">
            <td><span class="code">900</span></td>
            <td>Net à payer
                @if(abs($netPercu - $netCalcule) > 0.5)
                    <span style="font-weight:normal;font-size:8px;color:#c5d0b8;">
                        (calculé {{ $fmtNum($netCalcule) }} · perçu ajusté)
                    </span>
                @endif
            </td>
            <td></td>
            <td class="right" colspan="2">{{ $fmt($netPercu) }}</td>
        </tr>
    </tbody>
</table>

@if($statut === 'paye')
<table class="bp-pay">
    <tr>
        <td style="width:25%;">
            <span class="k">Mode de paiement</span>
            <span class="v">{{ $modeLabel ? strtoupper($modeLabel) : strtoupper((string) $bulletin->mode_paiement) }}</span>
        </td>
        <td style="width:30%;">
            <span class="k">Référence</span>
            <span class="v">{{ $bulletin->reference_paiement ?: '—' }}</span>
        </td>
        <td style="width:25%;">
            <span class="k">Date de paiement</span>
            <span class="v">{{ $bulletin->paye_le?->format('d/m/Y') ?: '—' }}</span>
        </td>
        <td style="width:20%;">
            <span class="k">Montant versé</span>
            <span class="v">{{ $fmt($netPercu) }}</span>
        </td>
    </tr>
</table>
@endif

<div class="bp-legal">
    <strong style="color:#311f38;">Document confidentiel —</strong>
    Bulletin établi conformément au Code du travail ivoirien et à la Convention collective applicable.
    Conservez-le sans limitation de durée. En cas de litige, seul l’original archivé par l’employeur fait foi.
    Les montants sont exprimés en francs CFA (XOF).
</div>

<table class="bp-sign">
    <tr>
        <td class="pad-r">
            <div class="box">
                <p class="ttl">Signature du salarié</p>
                <p class="hint">Lu et approuvé · Date &amp; signature</p>
            </div>
        </td>
        <td class="pad-l">
            <div class="box">
                <p class="ttl">Cachet &amp; signature employeur</p>
                <p class="hint">{{ $brand['company_name'] }}</p>
            </div>
        </td>
    </tr>
</table>

@include('pdf.partials.footer', [
    'brand' => $brand,
    'footerRight' => 'Bulletin de paie · '.$periodeLabel.' · N° '.$refDoc.'<br>'.$generatedAt.' (Africa/Abidjan)',
])
</body>
</html>
