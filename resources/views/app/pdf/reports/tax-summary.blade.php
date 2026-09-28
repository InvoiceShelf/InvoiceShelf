@extends('app.pdf.reports.partials.layout')

@section('report-title', __('pdf_tax_report_label'))

{{-- The band names the net position rather than a fixed total, so the label is
     conditional and the value is always shown unsigned. --}}
@section('footer-label')
    @if ($netTaxAmount > 0)
        @lang('pdf_tax_payable_label')
    @elseif ($netTaxAmount < 0)
        @lang('pdf_tax_refundable_label')
    @else
        @lang('pdf_tax_balance_label')
    @endif
@endsection

{{-- Block form rather than the inline @section($name, $value) one: the inline
     form escapes what it is given, and format_money_pdf() returns markup. --}}
@section('footer-value'){!! format_money_pdf(abs($netTaxAmount), $currency) !!}@endsection

@section('report-body')
    <div class="report-section">
        <p class="report-section-heading">@lang('pdf_output_tax_label')</p>
        <table class="report-table">
            <thead>
                <tr>
                    <th>@lang('pdf_report_tax_type_label')</th>
                    <th class="report-amount report-col-amount">@lang('pdf_amount_label')</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($taxTypes as $tax)
                    <tr>
                        <td>{{ $tax->taxType->name }}</td>
                        <td class="report-amount">{!! format_money_pdf($tax->total_tax_amount, $currency) !!}</td>
                    </tr>
                @empty
                    <tr>
                        <td class="report-muted" colspan="2">@lang('pdf_report_no_records')</td>
                    </tr>
                @endforelse
                <tr class="report-total-row">
                    <td class="report-total">@lang('pdf_total')</td>
                    <td class="report-amount report-total">{!! format_money_pdf($totalTaxAmount, $currency) !!}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="report-section">
        <p class="report-section-heading">@lang('pdf_input_tax_label')</p>
        <table class="report-table">
            <thead>
                <tr>
                    <th>@lang('pdf_report_tax_type_label')</th>
                    <th class="report-amount report-col-amount">@lang('pdf_amount_label')</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($expenseTaxTypes as $tax)
                    <tr>
                        <td>{{ $tax->taxType->name }}</td>
                        <td class="report-amount">{!! format_money_pdf($tax->total_tax_amount, $currency) !!}</td>
                    </tr>
                @empty
                    <tr>
                        <td class="report-muted" colspan="2">@lang('pdf_report_no_records')</td>
                    </tr>
                @endforelse
                <tr class="report-total-row">
                    <td class="report-total">@lang('pdf_total')</td>
                    <td class="report-amount report-total">{!! format_money_pdf($totalExpenseTaxAmount, $currency) !!}</td>
                </tr>
            </tbody>
        </table>
    </div>
@endsection
