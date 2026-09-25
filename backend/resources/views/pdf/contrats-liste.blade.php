<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Liste des contrats</title>
    @include('pdf.partials.styles-liste')
</head>
<body>
@php
    $brand = $brand ?? \App\Support\PdfBrand::data();
    $canSeeSalaire = $canSeeSalaire ?? false;
    $fmt = static fn ($n) => $n === null || $n === ''
        ? '—'
        : number_format((float) $n, 0, ',', ' ');
    $typeLabels = [
        'cdi' => 'CDI',
        'cdd' => 'CDD',
        'prestation' => 'Prestation',
        'stage' => 'Stage',
    ];
    $statutLabels = [
        'actif' => 'Actif',
        'suspendu' => 'Suspendu',
        'termine' => 'Terminé',
        'resilie' => 'Résilié',
    ];
@endphp

@include('pdf.partials.header-liste', [
    'brand' => $brand,
    'docTitle' => 'Liste des contrats',
    'docSubtitle' => e($generatedAt).' · '.$total.' contrat'.($total > 1 ? 's' : ''),
])

@if($contrats->isEmpty())
    <div class="sis-empty">Aucun contrat pour ces critères.</div>
@else
    <table class="sis-table">
        <thead>
            <tr>
                <th style="width:3%;">#</th>
                <th style="width:11%;">Référence</th>
                <th style="width:14%;">Agent</th>
                <th style="width:8%;">Matricule</th>
                <th style="width:10%;">Ville</th>
                <th style="width:7%;">Type</th>
                <th style="width:8%;">Début</th>
                <th style="width:8%;">Fin</th>
                <th style="width:6%;">Durée</th>
                <th style="width:7%;">Statut</th>
                @if($canSeeSalaire)
                    <th style="width:9%;" class="right">Salaire brut</th>
                    <th style="width:9%;" class="right">Salaire net</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach($contrats as $i => $contrat)
                @php
                    $type = $contrat->type instanceof \BackedEnum ? $contrat->type->value : (string) $contrat->type;
                    $statut = is_string($contrat->statut) ? $contrat->statut : (string) $contrat->statut;
                    $agent = $contrat->agent;
                    $agentLabel = $agent
                        ? trim(($agent->prenom ?? '').' '.($agent->nom ?? ''))
                        : '—';
                    $ville = $agent?->relationLoaded('villeRef') && $agent->villeRef
                        ? $agent->villeRef->libelle
                        : '—';
                    $duree = $contrat->duree_mois;
                    $dureeLabel = $duree === null || $duree === ''
                        ? '—'
                        : ((int) $duree).' mois';
                @endphp
                <tr>
                    <td class="center">{{ $i + 1 }}</td>
                    <td>{{ $contrat->reference ?: '—' }}</td>
                    <td class="bold">{{ $agentLabel !== '' ? $agentLabel : '—' }}</td>
                    <td>{{ $agent?->matricule ?: '—' }}</td>
                    <td>{{ $ville }}</td>
                    <td>{{ $typeLabels[$type] ?? strtoupper($type) }}</td>
                    <td>{{ $contrat->date_debut?->format('d/m/Y') ?? '—' }}</td>
                    <td>{{ $contrat->date_fin?->format('d/m/Y') ?? '—' }}</td>
                    <td class="center">{{ $dureeLabel }}</td>
                    <td>{{ $statutLabels[$statut] ?? $statut }}</td>
                    @if($canSeeSalaire)
                        <td class="right">{{ $fmt($contrat->salaire_brut ?? $contrat->salaire) }}</td>
                        <td class="right">{{ $fmt($contrat->salaire_net) }}</td>
                    @endif
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
