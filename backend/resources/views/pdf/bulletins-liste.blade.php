<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Liste des bulletins de paie</title>
    @include('pdf.partials.styles-liste')
</head>
<body>
@php
    $brand = $brand ?? \App\Support\PdfBrand::data();
    $canSeeSalaire = $canSeeSalaire ?? false;
    $fmt = static fn ($n) => number_format((float) $n, 0, ',', ' ');
    $statutLabels = [
        'brouillon' => 'Brouillon',
        'valide' => 'Validé',
        'paye' => 'Payé',
    ];
@endphp

@include('pdf.partials.header-liste', [
    'brand' => $brand,
    'docTitle' => 'Bulletins de paie',
    'docSubtitle' => e($periodeLabel).' · '.e($generatedAt).' · '.$total.' bulletin'.($total > 1 ? 's' : '').' · Filtres : '.e($filtresLabel),
])

@if($bulletins->isEmpty())
    <div class="sis-empty">Aucun bulletin pour ces critères.</div>
@else
    <table class="sis-table">
        <thead>
            <tr>
                <th style="width:3%;">#</th>
                <th style="width:9%;">Matricule</th>
                <th style="width:15%;">Agent</th>
                <th style="width:11%;">Téléphone</th>
                <th style="width:12%;">GRADE</th>
                <th style="width:8%;">Statut</th>
                <th style="width:10%;">Mode</th>
                <th style="width:9%;">Payé le</th>
                @if($canSeeSalaire)
                    <th style="width:11%;" class="right">Brut</th>
                    <th style="width:12%;" class="right">Net / perçu</th>
                @else
                    <th style="width:23%;">Salaire perçu</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach($bulletins as $i => $bulletin)
                @php
                    $statut = $bulletin->statut instanceof \BackedEnum
                        ? $bulletin->statut->value
                        : (string) $bulletin->statut;
                    $details = is_array($bulletin->details) ? $bulletin->details : [];
                    $renseigne = array_key_exists('salaire_percu', $details)
                        || array_key_exists('salaire_renseigne_le', $details);
                    $mode = $bulletin->mode_paiement
                        ? \App\Support\ModePaiementRules::label((string) $bulletin->mode_paiement)
                        : '—';
                    $agent = $bulletin->agent;
                    $grade = $agent?->relationLoaded('grade') && $agent->grade
                        ? mb_strtoupper((string) $agent->grade->libelle)
                        : '—';
                @endphp
                <tr>
                    <td class="center">{{ $i + 1 }}</td>
                    <td>{{ $agent?->matricule ?: '—' }}</td>
                    <td class="bold">{{ $agent ? trim($agent->prenom.' '.$agent->nom) : '—' }}</td>
                    <td>{{ $agent?->telephone ?: '—' }}</td>
                    <td>{{ $grade }}</td>
                    <td>{{ $statutLabels[$statut] ?? $statut }}</td>
                    <td>{{ $statut === 'paye' ? $mode : '—' }}</td>
                    <td>{{ $bulletin->paye_le?->format('d/m/Y') ?: '—' }}</td>
                    @if($canSeeSalaire)
                        <td class="right">{{ $fmt($bulletin->salaire_brut) }}</td>
                        <td class="right">{{ $fmt($bulletin->salaire_net) }}</td>
                    @else
                        <td>{{ $renseigne || $statut === 'paye' ? 'Saisi' : 'En attente RH' }}</td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

<div class="sis-footer">
    <strong>{{ $brand['company_name'] }}</strong>
    — Bulletins {{ $periodeLabel }} — document confidentiel
</div>
</body>
</html>
