@extends('app.pdf.reports.partials.layout')

@section('report-title', __('pdf_purchases_report_label'))
@section('footer-label', __('pdf_cost_including_tax_label'))
@section('footer-value'){!! format_money_pdf($report['purchases']['gross'], $currency) !!}@endsection

@section('report-body')
    <p class="report-muted">{{ __('pdf_supplier_label') }}: {{ $supplier?->name ?? __('pdf_all_suppliers_label') }}</p>
    <div class="report-section">
        <p class="report-section-heading">@lang('pdf_cash_movement_label')</p>
        <p class="report-muted">@lang('pdf_purchases_cash_note')</p>
        <table class="report-table">
            <tbody>
                @foreach ([__('pdf_direct_expenses_label') => 'direct_expenses', __('pdf_supplier_payments_label') => 'supplier_payments', __('pdf_supplier_refunds_label') => 'supplier_refunds'] as $label => $key)
                    <tr><td>{{ $label }}</td><td class="report-amount">{!! format_money_pdf($report['cash'][$key], $currency) !!}</td></tr>
                @endforeach
                <tr class="report-total-row"><td class="report-total">@lang('pdf_net_cash_out_label')</td><td class="report-amount report-total">{!! format_money_pdf($report['cash']['net_cash_out'], $currency) !!}</td></tr>
            </tbody>
        </table>
    </div>
    <div class="report-section">
        <p class="report-section-heading">@lang('pdf_purchase_costs_label')</p>
        <table class="report-table">
            <thead><tr><th>@lang('pdf_category_label')</th><th class="report-amount">@lang('pdf_cost_before_tax_label')</th><th class="report-amount">@lang('pdf_tax_amount_label')</th><th class="report-amount">@lang('pdf_cost_including_tax_label')</th></tr></thead>
            <tbody>
                @forelse ($report['categories'] as $row)
                    <tr><td>{{ $row['name'] }}</td>@foreach (['net', 'tax', 'gross'] as $key)<td class="report-amount">{!! format_money_pdf($row[$key], $currency) !!}</td>@endforeach</tr>
                @empty
                    <tr><td class="report-muted" colspan="4">@lang('pdf_report_no_records')</td></tr>
                @endforelse
                <tr class="report-total-row"><td class="report-total">@lang('pdf_total')</td>@foreach (['net', 'tax', 'gross'] as $key)<td class="report-amount report-total">{!! format_money_pdf($report['purchases'][$key], $currency) !!}</td>@endforeach</tr>
            </tbody>
        </table>
    </div>
    <div class="report-section">
        <p class="report-section-heading">@lang('pdf_purchase_taxes_label')</p>
        <p class="report-muted">@lang('pdf_purchase_tax_basis_note')</p>
        <table class="report-table">
            <thead><tr><th>@lang('pdf_report_tax_type_label')</th><th class="report-amount">@lang('pdf_amount_label')</th></tr></thead>
            <tbody>
                @forelse ($report['taxes'] as $row)
                    <tr><td>{{ $row['name'] }}</td><td class="report-amount">{!! format_money_pdf($row['amount'], $currency) !!}</td></tr>
                @empty
                    <tr><td class="report-muted" colspan="2">@lang('pdf_report_no_records')</td></tr>
                @endforelse
                <tr class="report-total-row"><td class="report-total">@lang('pdf_total')</td><td class="report-amount report-total">{!! format_money_pdf($report['purchases']['tax'], $currency) !!}</td></tr>
            </tbody>
        </table>
    </div>
    <div class="report-section">
        <p class="report-section-heading">@lang('pdf_current_payables_label')</p>
        <p class="report-muted">{{ __('pdf_payables_as_of_note', ['date' => $as_of_date]) }}</p>
        <table class="report-table">
            <tbody>
                @foreach ([__('pdf_outstanding_label') => 'outstanding', __('pdf_overdue_label') => 'overdue', __('pdf_due_soon_label') => 'due_soon', __('pdf_due_later_label') => 'due_later', __('pdf_available_advances_label') => 'available_advances', __('pdf_available_credits_label') => 'available_credits'] as $label => $key)
                    <tr><td>{{ $label }}</td><td class="report-amount">{!! format_money_pdf($report['payables'][$key], $currency) !!}</td></tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="report-section">
        <p class="report-section-heading">@lang('pdf_open_bills_label')</p>
        <table class="report-table">
            <thead><tr><th>@lang('pdf_bill_label')</th><th>@lang('pdf_supplier_label')</th><th class="report-col-date">@lang('pdf_due_date_label')</th><th class="report-amount report-col-amount">@lang('pdf_outstanding_label')</th></tr></thead>
            <tbody>
                @forelse ($report['aging'] as $bill)
                    <tr><td>{{ $bill->number }}</td><td>{{ $bill->supplier?->name }}</td><td>{{ $bill->due_date ? \Carbon\CarbonImmutable::parse($bill->due_date)->translatedFormat($date_pattern) : '-' }}</td><td class="report-amount">{!! format_money_pdf($bill->due_amount, $bill->currency) !!}</td></tr>
                @empty
                    <tr><td class="report-muted" colspan="4">@lang('pdf_report_no_records')</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
