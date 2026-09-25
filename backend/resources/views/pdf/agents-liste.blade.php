<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Liste du personnel</title>
    @include('pdf.partials.styles-liste')
</head>
<body>
@php
    $brand = $brand ?? \App\Support\PdfBrand::data();
    $typeLabels = [
        'agent' => 'Agent posté',
        'controleur' => 'Contrôleur',
        'administration' => 'Administration',
        'rondier' => 'Contrôleur',
    ];
    $statutLabels = [
        'disponible' => 'Disponible',
        'en_activite' => 'En activité',
        'conge' => 'Congé',
        'malade' => 'Malade',
        'suspendu' => 'Suspendu',
        'archive' => 'Archivé',
        'en_mission' => 'En activité',
        'en_conge' => 'Congé',
    ];
@endphp

@include('pdf.partials.header-liste', [
    'brand' => $brand,
    'docTitle' => 'Liste du personnel',
    'docSubtitle' => e($generatedAt).' · '.$total.' fiche'.($total > 1 ? 's' : ''),
])

@if($agents->isEmpty())
    <div class="sis-empty">Aucun personnel pour ces critères.</div>
@else
    <table class="sis-table">
        <thead>
            <tr>
                <th style="width:4%;">#</th>
                <th style="width:10%;">Matricule</th>
                <th style="width:14%;">Nom</th>
                <th style="width:14%;">Prénom</th>
                <th style="width:12%;">Type</th>
                <th style="width:14%;">Grade</th>
                <th style="width:12%;">Ville</th>
                <th style="width:10%;">Téléphone</th>
                <th style="width:10%;">Statut</th>
            </tr>
        </thead>
        <tbody>
            @foreach($agents as $i => $agent)
                @php
                    $type = $agent->type instanceof \BackedEnum ? $agent->type->value : (string) $agent->type;
                    $statut = $agent->statut instanceof \BackedEnum ? $agent->statut->value : (string) $agent->statut;
                    $ville = $agent->relationLoaded('villeRef') && $agent->villeRef
                        ? $agent->villeRef->libelle
                        : ($agent->ville ?: '—');
                    $grade = $agent->relationLoaded('grade') && $agent->grade
                        ? $agent->grade->libelle
                        : '—';
                @endphp
                <tr>
                    <td class="center">{{ $i + 1 }}</td>
                    <td>{{ $agent->matricule ?: '—' }}</td>
                    <td class="bold">{{ $agent->nom }}</td>
                    <td>{{ $agent->prenom }}</td>
                    <td>{{ $typeLabels[$type] ?? $type }}</td>
                    <td>{{ $grade }}</td>
                    <td>{{ $ville }}</td>
                    <td>{{ $agent->telephone ?: '—' }}</td>
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
