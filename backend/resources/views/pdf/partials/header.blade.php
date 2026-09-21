@php
    $brand = $brand ?? \App\Support\PdfBrand::data();
    $docEyebrow = $docEyebrow ?? 'Document officiel';
    $docTitle = $docTitle ?? 'SIS';
    $docSubtitle = $docSubtitle ?? null;
@endphp
<table class="sis-header">
    <tr>
        <td style="width: 56%;">
            <table style="border-collapse: collapse;">
                <tr>
                    @if($brand['logo_src'])
                        <td style="padding-right: 10px; vertical-align: middle;">
                            <img class="sis-logo" src="{{ $brand['logo_src'] }}" alt="SIS">
                        </td>
                    @endif
                    <td style="vertical-align: middle;">
                        <p class="sis-brand-name">SIS</p>
                        <p class="sis-brand-sub">{{ $brand['company_name'] }}</p>
                        <p class="sis-brand-tag">{{ $brand['tagline'] }}</p>
                    </td>
                </tr>
            </table>
        </td>
        <td style="width: 44%;" class="sis-doc-meta">
            <p class="sis-doc-eyebrow">{{ $docEyebrow }}</p>
            <p class="sis-doc-title">{{ $docTitle }}</p>
            @if($docSubtitle)
                <p class="sis-doc-sub">{!! $docSubtitle !!}</p>
            @endif
        </td>
    </tr>
</table>
<div class="sis-rule"></div>
<div class="sis-rule-accent"></div>
