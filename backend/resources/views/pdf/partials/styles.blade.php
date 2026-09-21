{{-- Charte PDF S.I.S — styles partagés (Dompdf-compatible) --}}
@php
    $brand = $brand ?? \App\Support\PdfBrand::data();
    $c = $brand['colors'];
@endphp
<style>
    @page {
        margin: 16mm 14mm 18mm 14mm;
    }

    * { box-sizing: border-box; }

    body {
        font-family: DejaVu Sans, Arial, sans-serif;
        font-size: 10.5px;
        line-height: 1.45;
        color: {{ $c['ink'] }};
        margin: 0;
        padding: 0;
    }

    .muted { color: {{ $c['ink_muted'] }}; }
    .faint { color: {{ $c['ink_faint'] }}; }
    .teal { color: {{ $c['teal'] }}; }
    .accent { color: {{ $c['accent'] }}; }
    .right { text-align: right; }
    .center { text-align: center; }
    .bold { font-weight: bold; }
    .upper { text-transform: uppercase; }

    /* —— Header —— */
    .sis-header {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 0;
    }

    .sis-header td {
        vertical-align: middle;
        padding: 0;
    }

    .sis-logo {
        width: 54px;
        height: 54px;
    }

    .sis-brand-name {
        margin: 0 0 2px 0;
        font-size: 15px;
        font-weight: bold;
        color: {{ $c['teal'] }};
        text-transform: uppercase;
        letter-spacing: 1.2px;
    }

    .sis-brand-sub {
        margin: 0 0 2px 0;
        font-size: 8.5px;
        color: {{ $c['ink_muted'] }};
        text-transform: uppercase;
        letter-spacing: 0.6px;
    }

    .sis-brand-tag {
        margin: 0;
        font-size: 8px;
        color: {{ $c['ink_faint'] }};
        letter-spacing: 0.4px;
    }

    .sis-doc-meta {
        text-align: right;
    }

    .sis-doc-eyebrow {
        margin: 0 0 4px 0;
        font-size: 8px;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 1.4px;
        color: {{ $c['accent'] }};
    }

    .sis-doc-title {
        margin: 0 0 4px 0;
        font-size: 16px;
        font-weight: bold;
        color: {{ $c['teal_dark'] }};
        text-transform: uppercase;
        letter-spacing: 0.8px;
        line-height: 1.25;
    }

    .sis-doc-sub {
        margin: 0;
        font-size: 9.5px;
        color: {{ $c['ink_muted'] }};
    }

    .sis-rule {
        height: 3px;
        background: {{ $c['teal'] }};
        margin: 12px 0 4px 0;
    }

    .sis-rule-accent {
        height: 1.5px;
        background: {{ $c['accent'] }};
        margin: 0 0 16px 0;
        width: 28%;
    }

    /* —— Panels / cards —— */
    .sis-panel {
        border: 1px solid {{ $c['border'] }};
        background: {{ $c['paper'] }};
        padding: 11px 13px;
    }

    .sis-panel-label {
        margin: 0 0 7px 0;
        font-size: 8px;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 1.2px;
        color: {{ $c['teal'] }};
    }

    .sis-panel p {
        margin: 0 0 3px 0;
        font-size: 10px;
        color: {{ $c['ink'] }};
    }

    .sis-panel .lbl {
        color: {{ $c['ink_muted'] }};
        font-size: 8.5px;
        text-transform: uppercase;
        letter-spacing: 0.4px;
    }

    /* —— KPI / highlight —— */
    .sis-kpi {
        width: 100%;
        border-collapse: collapse;
        margin: 0 0 14px 0;
    }

    .sis-kpi td {
        border: 1px solid {{ $c['border'] }};
        background: {{ $c['white'] }};
        padding: 10px 12px;
        vertical-align: top;
    }

    .sis-kpi .k {
        display: block;
        font-size: 7.5px;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: {{ $c['ink_faint'] }};
        margin-bottom: 3px;
    }

    .sis-kpi .v {
        font-size: 13px;
        font-weight: bold;
        color: {{ $c['teal'] }};
    }

    .sis-hero {
        width: 100%;
        border-collapse: collapse;
        margin: 0 0 16px 0;
        background: {{ $c['teal'] }};
        color: {{ $c['white'] }};
    }

    .sis-hero td {
        padding: 14px 16px;
        vertical-align: middle;
    }

    .sis-hero .k {
        display: block;
        font-size: 8px;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 1.4px;
        color: #c5d0b8;
        margin-bottom: 4px;
    }

    .sis-hero .v {
        font-size: 22px;
        font-weight: bold;
        letter-spacing: 0.5px;
    }

    .sis-hero .side {
        text-align: right;
        font-size: 9px;
        color: #d5ddd0;
        line-height: 1.5;
    }

    /* —— Data tables —— */
    table.sis-table {
        width: 100%;
        border-collapse: collapse;
        margin: 0 0 14px 0;
    }

    table.sis-table thead th {
        background: {{ $c['teal'] }};
        color: {{ $c['white'] }};
        font-size: 8px;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.7px;
        padding: 8px 8px;
        text-align: left;
        border: none;
    }

    table.sis-table thead th.right {
        text-align: right;
    }

    table.sis-table thead th.center {
        text-align: center;
    }

    table.sis-table tbody td {
        padding: 7px 8px;
        border-bottom: 1px solid {{ $c['border'] }};
        font-size: 10px;
        vertical-align: top;
        color: {{ $c['ink'] }};
    }

    table.sis-table tbody tr:nth-child(even) td {
        background: {{ $c['paper'] }};
    }

    table.sis-table tbody tr.sis-total td {
        background: {{ $c['paper_muted'] }};
        font-weight: bold;
        border-top: 2px solid {{ $c['teal'] }};
        border-bottom: none;
        font-size: 11px;
        color: {{ $c['teal_dark'] }};
        padding: 10px 8px;
    }

    table.sis-table tbody tr.sis-subtotal td {
        background: {{ $c['paper'] }};
        font-weight: bold;
        color: {{ $c['ink'] }};
    }

    table.sis-table.bordered {
        border: 1px solid {{ $c['border'] }};
    }

    table.sis-table.bordered thead th {
        border-right: 1px solid {{ $c['teal_dark'] }};
    }

    table.sis-table.bordered thead th:last-child {
        border-right: none;
    }

    table.sis-table.bordered tbody td {
        border-right: 1px solid {{ $c['border'] }};
    }

    table.sis-table.bordered tbody td:last-child {
        border-right: none;
    }

    .sis-empty {
        text-align: center;
        color: {{ $c['ink_muted'] }};
        padding: 28px 12px;
        border: 1px dashed {{ $c['border'] }};
        background: {{ $c['paper'] }};
        margin: 8px 0 14px 0;
    }

    /* —— Section titles —— */
    .sis-section {
        margin: 18px 0 8px 0;
        font-size: 9px;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 1.2px;
        color: {{ $c['teal'] }};
        border-bottom: 1px solid {{ $c['border'] }};
        padding-bottom: 5px;
    }

    /* —— Signature / notes —— */
    .sis-note {
        margin: 10px 0;
        padding: 10px 12px;
        background: {{ $c['paper'] }};
        border-left: 3px solid {{ $c['accent'] }};
        font-size: 10px;
        color: {{ $c['ink'] }};
    }

    .sis-sign-box {
        border: 1px solid {{ $c['border'] }};
        background: {{ $c['paper'] }};
        padding: 12px 14px;
        min-height: 90px;
    }

    .sis-sign-label {
        margin: 0 0 6px 0;
        font-size: 8px;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: {{ $c['teal'] }};
    }

    /* —— Footer —— */
    .sis-footer {
        position: fixed;
        left: 0;
        right: 0;
        bottom: 0;
        border-top: 1.5px solid {{ $c['teal'] }};
        padding-top: 7px;
        font-size: 7.5px;
        line-height: 1.4;
        color: {{ $c['ink_muted'] }};
    }

    .sis-footer-table {
        width: 100%;
        border-collapse: collapse;
    }

    .sis-footer-table td {
        padding: 0;
        vertical-align: top;
    }

    .sis-footer strong {
        color: {{ $c['teal'] }};
        font-size: 8px;
    }

    .sis-footer-spacer {
        height: 34px;
    }

    .sis-badge {
        display: inline-block;
        padding: 2px 7px;
        background: {{ $c['paper_muted'] }};
        color: {{ $c['teal'] }};
        font-size: 8px;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        border: 1px solid {{ $c['border'] }};
    }
</style>
