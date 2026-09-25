<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Liste des clients</title>
    @include('pdf.partials.styles-liste')
</head>
<body>
@php
    $brand = $brand ?? \App\Support\PdfBrand::data();
    $typeLabels = [
        'entreprise' => 'Entreprise',
        'particulier' => 'Particulier',
    ];
    $statutLabels = [
        'actif' => 'Actif',
        'suspendu' => 'Suspendu',
        'resilie' => 'Résilié',
    ];
@endphp

@include('pdf.partials.header-liste', [
    'brand' => $brand,
    'docTitle' => 'Liste des clients',
    'docSubtitle' => e($generatedAt).' · '.$total.' client'.($total > 1 ? 's' : ''),
])

@if($clients->isEmpty())
    <div class="sis-empty">Aucun client pour ces critères.</div>
@else
    <table class="sis-table">
        <thead>
            <tr>
                <th style="width:4%;">#</th>
                <th style="width:22%;">Nom / Raison sociale</th>
                <th style="width:10%;">Type</th>
                <th style="width:12%;">Contact</th>
                <th style="width:11%;">Téléphone</th>
                <th style="width:16%;">Email</th>
                <th style="width:15%;">Adresse</th>
                <th style="width:6%;">Sites</th>
                <th style="width:8%;">Statut</th>
            </tr>
        </thead>
        <tbody>
            @foreach($clients as $i => $client)
                @php
                    $type = $client->type instanceof \BackedEnum ? $client->type->value : (string) $client->type;
                    $statut = $client->statut instanceof \BackedEnum ? $client->statut->value : (string) $client->statut;
                @endphp
                <tr>
                    <td class="center">{{ $i + 1 }}</td>
                    <td class="bold">{{ $client->raison_sociale }}</td>
                    <td>{{ $typeLabels[$type] ?? $type }}</td>
                    <td>{{ $client->personne_contact ?: '—' }}</td>
                    <td>{{ $client->telephone ?: '—' }}</td>
                    <td>{{ $client->email ?: '—' }}</td>
                    <td>{{ $client->adresse ?: '—' }}</td>
                    <td class="center">{{ (int) ($client->sites_count ?? 0) }}</td>
                    <td>{{ $statutLabels[$statut] ?? $statut }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

<div class="sis-footer">
    <table class="sis-footer-table">
        <tr>
            <td>{{ $brand['company_name'] }}</td>
            <td style="text-align:right;">{{ $generatedAt }} · {{ $total }} ligne{{ $total > 1 ? 's' : '' }}</td>
        </tr>
    </table>
</div>
</body>
</html>
