<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Bulletin de paie</title>
    @include('pdf.partials.styles')
    <style>
        @page { margin: 14mm 12mm 16mm 12mm; }
        body { font-size: 10px; }
        .sis-hero .v { font-size: 18px; }
        .sis-hero td { padding: 10px 14px; }
        .sis-panel { padding: 9px 11px; }
        .sis-section { margin: 12px 0 6px 0; }
        .sis-note { margin: 8px 0; padding: 8px 10px; font-size: 9px; }
        .sis-sign-box { padding: 10px 12px; min-height: 64px; }
        .sis-kpi td { padding: 7px 10px; }
        .sis-kpi .v { font-size: 12px; }
        table.sis-table tbody td { padding: 5px 7px; font-size: 9.5px; }
        table.sis-table tbody tr.sis-total td { padding: 7px; font-size: 10.5px; }
        .sis-logo { width: 48px; height: 48px; }
        .sis-doc-title { font-size: 14px; }
        .sis-rule { margin: 8px 0 3px 0; }
        .sis-rule-accent { margin: 0 0 10px 0; }
    </style>
</head>
<body>
@php
    $brand = \App\Support\PdfBrand::data();
    $fmt = static fn ($n) => number_format((float) $n, 0, ',', ' ').' FCFA';
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
@endphp

@include('pdf.partials.header', [
    'brand' => $brand,
    'docEyebrow' => 'Ressources humaines',
    'docTitle' => 'Bulletin de paie',
    'docSubtitle' => 'Période '.$periodeLabel.' · Généré le '.$generatedAt,
])

<table class="sis-hero">
    <tr>
        <td style="width: 62%;">
            <span class="k">Net à payer</span>
            <span class="v">{{ $fmt($bulletin->salaire_net) }}</span>
        </td>
        <td class="side" style="width: 38%;">
            Statut : <strong style="color:#fff;">{{ $statutLabel }}</strong><br>
            @if($bulletin->paye_le)
                Payé le {{ $bulletin->paye_le->format('d/m/Y') }}<br>
            @endif
            Réf. {{ substr((string) $bulletin->id, 0, 8) }}…
        </td>
    </tr>
</table>

<table style="width:100%; border-collapse:collapse; margin-bottom:10px;">
    <tr>
        <td style="width:50%; padding-right:6px; vertical-align:top;">
            <div class="sis-panel">
                <p class="sis-panel-label">Salarié</p>
                <p style="font-size:11px; font-weight:bold; margin-bottom:4px;">
                    {{ $bulletin->agent->prenom }} {{ $bulletin->agent->nom }}
                </p>
                <p><span class="lbl">Matricule</span> · {{ $bulletin->agent->matricule ?: '—' }}
                    @if($bulletin->agent->cnps)
                        · <span class="lbl">CNPS</span> {{ $bulletin->agent->cnps }}
                    @endif
                </p>
                @if($bulletin->agent->date_embauche)
                    <p style="margin-top:2px;"><span class="lbl">Embauche</span> · {{ $bulletin->agent->date_embauche->format('d/m/Y') }}</p>
                @endif
            </div>
        </td>
        <td style="width:50%; padding-left:6px; vertical-align:top;">
            <div class="sis-panel">
                <p class="sis-panel-label">Contrat &amp; période</p>
                @if($bulletin->contrat)
                    <p>
                        <span class="lbl">Réf.</span> {{ $bulletin->contrat->reference ?: '—' }}
                        @if($typeLabel) · <span class="lbl">Type</span> {{ $typeLabel }} @endif
                    </p>
                    @if($bulletin->contrat->date_debut)
                        <p style="margin-top:2px;">
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
                    <p class="muted">Aucun contrat lié</p>
                @endif
                <p style="margin-top:2px;"><span class="lbl">Période</span> · {{ $periodeLabel }}</p>
            </div>
        </td>
    </tr>
</table>

<p class="sis-section">Détail de rémunération</p>

<table class="sis-table bordered">
    <thead>
        <tr>
            <th style="width:62%;">Libellé</th>
            <th class="right" style="width:38%;">Montant</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Salaire brut</td>
            <td class="right">{{ $fmt($bulletin->salaire_brut) }}</td>
        </tr>
        <tr>
            <td>Retenue CNPS (salarié)</td>
            <td class="right">− {{ $fmt($bulletin->retenue_cnps) }}</td>
        </tr>
        <tr>
            <td>Impôt général sur le revenu (IGR)
                @if(isset($details['parts_igr']))
                    <span class="faint"> · {{ $details['parts_igr'] }} part(s)</span>
                @endif
            </td>
            <td class="right">− {{ $fmt($bulletin->montant_igr) }}</td>
        </tr>
        <tr class="sis-total">
            <td>Net à payer</td>
            <td class="right">{{ $fmt($bulletin->salaire_net) }}</td>
        </tr>
    </tbody>
</table>

<div class="sis-note">
    <strong>Confidentiel —</strong>
    Bulletin établi conformément à la réglementation ivoirienne. Conservez-le.
    En cas de litige, seul l’original archivé par la société fait foi.
</div>

<table style="width:100%; border-collapse:collapse; margin-top:10px;">
    <tr>
        <td style="width:48%; padding-right:6px; vertical-align:top;">
            <div class="sis-sign-box">
                <p class="sis-sign-label">Signature salarié</p>
                <p class="faint" style="margin:22px 0 0 0; font-size:8px;">Lu et approuvé</p>
            </div>
        </td>
        <td style="width:48%; padding-left:6px; vertical-align:top;">
            <div class="sis-sign-box">
                <p class="sis-sign-label">Cachet &amp; signature employeur</p>
                <p class="faint" style="margin:22px 0 0 0; font-size:8px;">{{ $brand['company_name'] }}</p>
            </div>
        </td>
    </tr>
</table>

@include('pdf.partials.footer', [
    'brand' => $brand,
    'footerRight' => 'Bulletin de paie · '.$periodeLabel.'<br>'.$generatedAt.' (Africa/Abidjan)',
])
</body>
</html>
