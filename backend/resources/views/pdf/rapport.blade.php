<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ $dataset['title'] ?? 'Rapport' }}</title>
    @include('pdf.partials.styles')
    <style>
        @page { margin: 16mm 12mm 18mm 12mm; size: A4 landscape; }
        body { font-size: 9.5px; }
        table.sis-table thead th { font-size: 7.5px; padding: 6px 5px; }
        table.sis-table tbody td { font-size: 8.5px; padding: 5px; }
    </style>
</head>
<body>
@php
    $brand = \App\Support\PdfBrand::data();
    $title = $dataset['title'] ?? 'Rapport';
    $subtitle = $dataset['subtitle'] ?? '';
    $period = $dataset['period_label'] ?? null;
    $columns = $dataset['columns'] ?? [];
    $rows = $dataset['rows'] ?? [];
    $summary = $dataset['summary'] ?? [];
    $typeLabel = strtoupper((string) ($rapport->type instanceof \BackedEnum
        ? $rapport->type->value
        : $rapport->type));
    $formatLabel = strtoupper((string) ($rapport->format instanceof \BackedEnum
        ? $rapport->format->value
        : $rapport->format));
@endphp

@include('pdf.partials.header', [
    'brand' => $brand,
    'docEyebrow' => 'Reporting opérationnel',
    'docTitle' => $title,
    'docSubtitle' => $subtitle !== '' ? e($subtitle) : null,
])

<table style="width:100%; border-collapse:collapse; margin-bottom:12px;">
    <tr>
        <td style="width:50%; padding-right:6px; vertical-align:top;">
            <div class="sis-panel">
                <p class="sis-panel-label">Paramètres d’export</p>
                <p><span class="lbl">Type</span><br>{{ $typeLabel }}</p>
                <p style="margin-top:4px;"><span class="lbl">Format</span><br>{{ $formatLabel }}</p>
                <p style="margin-top:4px;">
                    <span class="lbl">Période</span><br>
                    {{ $period ?: 'Non filtrée' }}
                </p>
            </div>
        </td>
        <td style="width:50%; padding-left:6px; vertical-align:top;">
            <div class="sis-panel">
                <p class="sis-panel-label">Document</p>
                <p><span class="lbl">Généré le</span><br>{{ $generatedAt }} (Africa/Abidjan)</p>
                <p style="margin-top:4px;"><span class="lbl">Lignes</span><br>{{ count($rows) }}</p>
                <p style="margin-top:4px;">
                    <span class="lbl">Réf. export</span><br>
                    {{ substr((string) $rapport->id, 0, 8) }}…
                </p>
            </div>
        </td>
    </tr>
</table>

@if(count($summary) > 0)
    <table class="sis-kpi">
        <tr>
            @foreach($summary as $item)
                <td>
                    <span class="k">{{ $item['label'] }}</span>
                    <span class="v">{{ $item['value'] }}</span>
                </td>
            @endforeach
            @for($i = count($summary); $i < 3; $i++)
                <td>
                    <span class="k">&nbsp;</span>
                    <span class="v muted">—</span>
                </td>
            @endfor
        </tr>
    </table>
@endif

@if(count($rows) === 0)
    <div class="sis-empty">
        Aucune donnée pour ces critères sur la période sélectionnée.
    </div>
@else
    <table class="sis-table bordered">
        <thead>
            <tr>
                @foreach($columns as $col)
                    <th>{{ $col }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $row)
                <tr>
                    @foreach($row as $cell)
                        <td>{{ $cell }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

@include('pdf.partials.footer', [
    'brand' => $brand,
    'footerRight' => $typeLabel.' · '.$generatedAt.'<br>Usage interne / client',
])
</body>
</html>
