@php
    $brand = $brand ?? \App\Support\PdfBrand::data();
    $footerRight = $footerRight ?? null;
@endphp
<div class="sis-footer-spacer"></div>
<div class="sis-footer">
    <table class="sis-footer-table">
        <tr>
            <td style="width: 68%;">
                <strong>{{ $brand['company_name'] }}</strong><br>
                {{ $brand['company_legal'] }}
            </td>
            <td style="width: 32%; text-align: right;">
                @if($footerRight)
                    {!! $footerRight !!}
                @else
                    Document généré automatiquement<br>
                    Usage confidentiel
                @endif
            </td>
        </tr>
    </table>
</div>
