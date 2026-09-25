@php
    $brand = $brand ?? \App\Support\PdfBrand::data();
    $docTitle = $docTitle ?? 'Liste';
    $docSubtitle = $docSubtitle ?? null;
@endphp
<table class="sis-list-head">
    <tr>
        <td style="width:55%;">
            <p class="sis-list-brand">SIS</p>
            <p class="sis-list-sub">{{ $brand['company_name'] }}</p>
        </td>
        <td style="width:45%;">
            <p class="sis-list-title">{{ $docTitle }}</p>
            @if($docSubtitle)
                <p class="sis-list-meta">{!! $docSubtitle !!}</p>
            @endif
        </td>
    </tr>
</table>
<hr class="sis-rule">
