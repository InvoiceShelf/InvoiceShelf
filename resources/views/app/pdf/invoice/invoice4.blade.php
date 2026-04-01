<!DOCTYPE html>
<html>

<head>
    <title>@lang('pdf_invoice_label') - {{ $invoice->invoice_number }}</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <style type="text/css">
        body {
            font-family: "DejaVu Sans";
            font-size: 12px;
            color: #333333;
            margin: 0;
            padding: 0;
        }

        html {
            margin: 0;
            padding: 0;
        }

        /* ===== HEADER ===== */
        .header {
            padding: 30px 30px 20px 30px;
        }

        .header-logo {
            width: 150px;
            height: 100px;
        }

        /* ===== ADDRESS BLOCK ===== */
        .address-section {
            padding: 10px 30px 10px 30px;
            width: 100%;
        }

        .bill-to-block {
            float: left;
            width: 50%;
        }

        .company-block {
            float: right;
            width: 45%;
            text-align: right;
        }

        .label-small {
            font-size: 11px;
            color: #555555;
            margin-bottom: 4px;
        }

        .customer-name {
            font-size: 14px;
            font-weight: bold;
            color: #000000;
            margin: 4px 0 6px 0;
        }

        .address-text {
            font-size: 11px;
            line-height: 18px;
            color: #333333;
        }

        .company-text {
            font-size: 11px;
            line-height: 18px;
            color: #333333;
            text-align: left;
        }

        /* ===== INVOICE META (date / due date) ===== */
        .meta-section {
            padding: 10px 30px 5px 30px;
            clear: both;
        }

        .meta-table {
            float: right;
            border-collapse: collapse;
        }

        .meta-label {
            font-size: 11px;
            color: #555555;
            padding-right: 8px;
            text-align: right;
        }

        .meta-value {
            font-size: 11px;
            color: #333333;
            text-align: right;
        }

        /* ===== INVOICE NUMBER ===== */
        .invoice-number-section {
            padding: 15px 30px 8px 30px;
            clear: both;
            font-size: 12px;
        }

        .invoice-number-value {
            font-weight: bold;
        }

        /* ===== ITEMS TABLE ===== */
        .items-table {
            padding: 0 30px 10px 30px;
            page-break-before: avoid;
            page-break-after: auto;
            width: 100%;
        }

        .items-table table {
            width: 100%;
            border-collapse: collapse;
        }

        .item-table-heading {
            font-size: 11px;
            font-weight: bold;
            color: #333333;
            padding: 8px 5px;
            border-top: 1px solid #cccccc;
            border-bottom: 1px solid #cccccc;
            text-align: left;
        }

        .item-table-heading.text-right {
            text-align: right;
        }

        .item-table-heading.text-center {
            text-align: center;
        }

        .item-cell {
            font-size: 11px;
            padding: 10px 5px;
            color: #333333;
            border-bottom: 1px solid #eeeeee;
            vertical-align: top;
        }

        .item-cell.text-right {
            text-align: right;
        }

        .item-cell.text-center {
            text-align: center;
        }

        .item-description {
            color: #777777;
            font-size: 10px;
            line-height: 14px;
            display: block;
            margin-top: 3px;
        }

        /* ===== TOTALS ===== */
        .total-display-container {
            padding: 0 30px;
            page-break-inside: avoid;
        }

        .total-display-table {
            float: right;
            border-collapse: collapse;
            width: auto;
            margin-top: 20px;
        }

        .total-table-attribute-label {
            font-size: 12px;
            color: #333333;
            text-align: left;
            padding: 4px 40px 4px 0;
        }

        .total-table-attribute-value {
            font-size: 12px;
            color: #333333;
            text-align: right;
            padding: 4px 0;
        }

        .total-row-bold td {
            font-size: 13px;
            font-weight: bold;
            padding-top: 6px;
        }

        .total-row-colored {
            color: #5851D8 !important;
        }

        /* ===== NOTES ===== */
        .notes-section {
            padding: 20px 30px;
            clear: both;
            page-break-inside: avoid;
        }

        .notes-label {
            font-size: 13px;
            font-weight: bold;
            color: #000000;
            margin-bottom: 6px;
        }

        .notes-content {
            font-size: 11px;
            line-height: 18px;
            color: #555555;
        }

        /* ===== FOOTER ===== */
        .footer-section {
            position: fixed;
            align-items: center;
            bottom: 30px;
            left: 0;
            width: 90%;
            padding: 0 30px;
        }

        .footer-table {
            width: 100%;
            border-collapse: collapse;
        }

        .footer-table td {
            font-size: 9px;
            color: #333333;
            padding: 4px 8px;
            border: 1px solid #cccccc;
            vertical-align: middle;
        }

        /* ===== HELPERS ===== */
        .clearfix {
            clear: both;
        }

        .text-right {
            text-align: right;
        }

        .text-left {
            text-align: left;
        }

        .text-center {
            text-align: center;
        }

        .border-0 {
            border: none;
        }

        .pr-20 {
            padding-right: 20px;
        }

        .pl-0 {
            padding-left: 0;
        }

        .pl-10 {
            padding-left: 10px;
        }

        .py-2 {
            padding-top: 2px;
            padding-bottom: 2px;
        }

        .py-3 {
            padding-top: 3px;
            padding-bottom: 3px;
        }

        .py-8 {
            padding-top: 8px;
            padding-bottom: 8px;
        }

        .total-border-left {
            border: 1px solid #E8E8E8 !important;
            border-right: 0 !important;
            padding: 8px !important;
        }

        .total-border-right {
            border: 1px solid #E8E8E8 !important;
            border-left: 0 !important;
            padding: 8px !important;
        }
    </style>
    @if (App::isLocale('th'))
        @include('app.pdf.locale.th')
    @endif
</head>

<body>

    {{-- ===== LOGO ===== --}}
    <div class="header">
        @if ($logo)
            <img class="header-logo" src="{{ \App\Space\ImageUtils::toBase64Src($logo) }}" alt="Company Logo">
        @elseif ($invoice->customer->company)
            <h1 class="header-logo" style="padding-top: 0px;">
                {{ $invoice->customer->company->name }}
            </h1>
        @endif
    </div>

    {{-- ===== BILLING ADDRESS (left) + COMPANY ADDRESS (right) ===== --}}
    <div class="address-section">
        <div class="bill-to-block">
            @if ($billing_address)
                @if ($shipping_address)
                    <div class="label-small">@lang('pdf_bill_to')</div>
                @endif
                <div class="address-text">{!! $billing_address !!}</div>
            @endif
            @if ($shipping_address)
                <div class="label-small" style="margin-top: 12px;">@lang('pdf_ship_to')</div>
                <div class="address-text">{!! $shipping_address !!}</div>
            @endif
        </div>

        <div class="company-block">
            <div class="company-text">{!! $company_address !!}</div>
        </div>

    </div>

    <div class="clearfix"></div>

    {{-- ===== INVOICE DATE / DUE DATE ===== --}}
    <div class="meta-section">
        <table class="meta-table">
            <tr>
                <td class="meta-label">@lang('pdf_invoice_date'):</td>
                <td class="meta-value">{{ $invoice->formattedInvoiceDate }}</td>
            </tr>
            <tr>
                <td class="meta-label">@lang('pdf_invoice_due_date'):</td>
                <td class="meta-value">{{ $invoice->formattedDueDate }}</td>
            </tr>
        </table>
    </div>

    <div class="clearfix"></div>

    {{-- ===== INVOICE NUMBER ===== --}}
    <div class="invoice-number-section">
        @lang('pdf_invoice_number'): <span class="invoice-number-value">{{ $invoice->invoice_number }}</span>
    </div>

    {{-- ===== ITEMS TABLE + TOTALS ===== --}}
    <div style="position: relative; clear: both;">
        @include('app.pdf.invoice.partials.table')
    </div>

    {{-- ===== NOTES ===== --}}
    @if ($notes)
        <div class="notes-section">
            <div class="notes-label">@lang('pdf_notes')</div>
            <div class="notes-content">{!! $notes !!}</div>
        </div>
    @endif

    {{-- ===== FOOTER ===== --}}
    <div class="footer-section">
        <table class="footer-table">
            <tr>
                <td>ExactBalance</td>
                <td>E-mail:</td>
                <td>Adres</td>
                <td>KvK nr: 89329988</td>
            </tr>
            <tr>
                <td>www.exactbalance.nl</td>
                <td>info@exactbalance.nl</td>
                <td>Parkstraat 8B Arnhem 6988AK</td>
                <td>BTW-nr: NL004721754B84</td>
            </tr>
        </table>
    </div>

</body>

</html>