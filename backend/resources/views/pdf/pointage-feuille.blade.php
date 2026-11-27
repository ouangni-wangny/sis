<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Feuille de pointage</title>
    @include('pdf.partials.styles-liste')
    <style>
        @page { margin: 8mm 6mm 10mm 6mm; size: A4 landscape; }
        .categorie { page-break-after: always; }
        .categorie:last-child { page-break-after: auto; }
        .categorie-titre {
            text-align: center;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 0 0 4px 0;
        }
        table.feuille { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.feuille th, table.feuille td {
            border: 0.5px solid #555;
            padding: 1.5px 0;
            text-align: center;
            font-size: 6.5px;
        }
        table.feuille thead th { background: #eef3c7; font-weight: bold; }
        table.feuille td.nom { text-align: left; padding-left: 2px; font-size: 6.5px; white-space: nowrap; }
        table.feuille td.absent { background: #d9d9d9; font-weight: bold; }
        table.feuille td.double { font-weight: bold; }
        table.feuille td.nc { color: #999; font-size: 5px; }
        table.feuille td.total { font-weight: bold; font-size: 7.5px; }
        .legende { margin-top: 4px; font-size: 6.5px; color: #555; }
    </style>
</head>
<body>
@php
    $brand = $brand ?? \App\Support\PdfBrand::data();
    $contrats = ['cdi' => 'CDI', 'cdd' => 'CDD', 'stage' => 'STAGIAIRE', 'prestation' => 'PRESTATION'];
    $jours = $feuille['jours'];
    $periode = \Carbon\Carbon::parse($feuille['date_debut'])->format('d/m/Y')
        .' au '.\Carbon\Carbon::parse($feuille['date_fin'])->format('d/m/Y');
    $nbJours = count($jours);
    // Largeurs (%) : nom + statut + jours + total + observations = 100.
    $wNom = 17; $wStatut = 6; $wTotal = 4; $wAbs = 4; $wObs = 6;
    $wJour = round((100 - $wNom - $wStatut - $wTotal - $wAbs - $wObs) / max(1, $nbJours), 3);
@endphp

@if(empty($feuille['categories']))
    @include('pdf.partials.header-liste', [
        'brand' => $brand,
        'docTitle' => 'Feuille de pointage',
        'docSubtitle' => e($periode),
    ])
    <div class="sis-empty">Aucun agent sur la période.</div>
@endif

@php
    // Une page A4 par paquet d’agents : DomPDF est bien plus rapide sur des
    // petits tableaux que sur un seul tableau de plusieurs centaines de lignes.
    $parPage = 34;
    $pages = [];
    foreach ($feuille['categories'] as $categorie) {
        $paquets = array_chunk($categorie['agents'], $parPage);
        foreach ($paquets as $i => $agents) {
            $pages[] = [
                'categorie' => $categorie,
                'agents' => $agents,
                'suite' => $i > 0,
                'debut' => $i * $parPage,
            ];
        }
    }
    $nbPages = count($pages);
@endphp

@foreach($pages as $n => $page)
    @php $categorie = $page['categorie']; @endphp
    <div class="categorie">
        @include('pdf.partials.header-liste', [
            'brand' => $brand,
            'docTitle' => 'Feuille de pointage',
            'docSubtitle' => e('Du '.$periode).' · page '.($n + 1).' / '.$nbPages,
        ])
        <p class="categorie-titre">
            {{ $categorie['libelle'] }}{{ $page['suite'] ? ' (suite)' : '' }}
            <span style="font-weight:normal;font-size:8px;letter-spacing:0;text-transform:none;">
                — {{ count($categorie['agents']) }} agent{{ count($categorie['agents']) > 1 ? 's' : '' }}
            </span>
        </p>
        <p class="legende" style="margin:0 0 4px 0;">
            <b>Légende :</b>
            <b>1</b> = présent · <b>2</b> = présent sur deux vacations le même jour ·
            <b>A</b> = absent · <b>NC</b> = vacation prévue mais non contrôlée (non comptée) ·
            case vide = pas de vacation. Jours de travail effectués = total des présences. Jours d’absence = nombre de jours marqués A.
        </p>

        <table class="feuille">
            <thead>
                <tr>
                    <th rowspan="2" style="width:3%;">N°</th>
                    <th rowspan="2" style="width:{{ $wNom - 3 }}%;">NOM ET PRÉNOMS</th>
                    <th rowspan="2" style="width:{{ $wStatut }}%;">STATUT</th>
                    <th colspan="{{ $nbJours }}">ÉTAT DE PRÉSENCE</th>
                    <th rowspan="2" style="width:{{ $wTotal }}%;">JOURS DE TRAVAIL EFFECTUÉS</th>
                    <th rowspan="2" style="width:{{ $wAbs }}%;">JOURS D’ABSENCE</th>
                    <th rowspan="2" style="width:{{ $wObs }}%;">OBSERVATIONS</th>
                </tr>
                <tr>
                    @foreach($jours as $jour)
                        <th style="width:{{ $wJour }}%;">{{ $jour['jour'] }}<br>{{ (int) substr($jour['date'], 8, 2) }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($page['agents'] as $i => $agent)
                    <tr>
                        <td>{{ $page['debut'] + $i + 1 }}</td>
                        <td class="nom">{{ $agent['nom'] }}</td>
                        <td>{{ $contrats[$agent['contrat']] ?? '' }}</td>
                        @foreach($jours as $jour)
                            @php $v = $agent['jours'][$jour['date']] ?? null; @endphp
                            <td class="{{ $v === 'A' ? 'absent' : ($v === 'NC' ? 'nc' : (is_numeric($v) && (int) $v > 1 ? 'double' : '')) }}">{{ $v }}</td>
                        @endforeach
                        <td class="total">{{ $agent['total'] }}</td>
                        <td class="total">{{ $agent['absences'] }}</td>
                        <td></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p class="legende">Édité le {{ $generatedAt }} — {{ $brand['company_name'] }}</p>
    </div>
@endforeach
</body>
</html>
