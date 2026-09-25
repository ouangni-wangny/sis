{{-- Styles allégés pour listes volumineuses (DomPDF) --}}
@php
    $brand = $brand ?? \App\Support\PdfBrand::data();
    $c = $brand['colors'];
@endphp
<style>
    @page { margin: 12mm 10mm 14mm 10mm; size: A4 landscape; }
    body {
        font-family: DejaVu Sans, Arial, sans-serif;
        font-size: 8px;
        line-height: 1.3;
        color: {{ $c['ink'] }};
        margin: 0;
        padding: 0;
    }
    .sis-list-head {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 8px;
    }
    .sis-list-head td { vertical-align: middle; padding: 0; }
    .sis-list-brand {
        font-size: 12px;
        font-weight: bold;
        color: {{ $c['teal'] }};
        text-transform: uppercase;
        letter-spacing: 0.8px;
        margin: 0;
    }
    .sis-list-sub {
        margin: 1px 0 0 0;
        font-size: 7.5px;
        color: {{ $c['ink_muted'] }};
    }
    .sis-list-title {
        margin: 0;
        font-size: 13px;
        font-weight: bold;
        color: {{ $c['ink'] }};
        text-align: right;
    }
    .sis-list-meta {
        margin: 2px 0 0 0;
        font-size: 7.5px;
        color: {{ $c['ink_muted'] }};
        text-align: right;
    }
    .sis-rule {
        border: 0;
        border-top: 1.5px solid {{ $c['teal'] }};
        margin: 6px 0 10px 0;
    }
    table.sis-table {
        width: 100%;
        border-collapse: collapse;
    }
    table.sis-table thead th {
        background: {{ $c['teal'] }};
        color: {{ $c['white'] }};
        font-size: 7px;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        padding: 4px 3px;
        text-align: left;
        border: 0.5px solid {{ $c['teal_dark'] }};
    }
    table.sis-table tbody td {
        font-size: 7.5px;
        padding: 3px;
        border: 0.5px solid {{ $c['border'] }};
        vertical-align: top;
    }
    table.sis-table tbody tr:nth-child(even) td {
        background: {{ $c['paper_muted'] }};
    }
    .center { text-align: center; }
    .bold { font-weight: bold; }
    .sis-empty {
        padding: 16px;
        text-align: center;
        color: {{ $c['ink_muted'] }};
        border: 1px solid {{ $c['border'] }};
    }
    .sis-footer {
        margin-top: 10px;
        font-size: 6.5px;
        color: {{ $c['ink_faint'] }};
        border-top: 0.5px solid {{ $c['border'] }};
        padding-top: 4px;
    }
    .sis-footer-table { width: 100%; border-collapse: collapse; }
</style>
