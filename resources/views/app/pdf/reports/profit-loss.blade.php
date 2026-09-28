@extends('app.pdf.reports.partials.layout')

@section('report-title', __('pdf_profit_loss_label'))
@section('footer-label', __('pdf_net_profit_label'))

{{-- Block form rather than the inline @section($name, $value) one: the inline
     form escapes what it is given, and format_money_pdf() returns markup. --}}
@section('footer-value'){!! format_money_pdf($income - $totalExpense, $currency) !!}@endsection

@section('report-body')
    <p class="report-muted">@lang('pdf_cash_basis_note')</p>
    <div class="report-section">
        <table class="report-table">
            <tr>
                <td>@lang('pdf_income_label')</td>
                <td class="report-amount report-total">{!! format_money_pdf($income, $currency) !!}</td>
            </tr>
        </table>
    </div>

    <div class="report-section">
        <p class="report-section-heading">@lang('pdf_expenses_label')</p>
        <table class="report-table">
            <thead>
                <tr>
                    <th>@lang('pdf_report_category_label')</th>
                    <th class="report-amount report-col-amount">@lang('pdf_amount_label')</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($expenseCategories as $expenseCategory)
                    <tr>
                        <td>{{ $expenseCategory->category->name }}</td>
                        <td class="report-amount">{!! format_money_pdf($expenseCategory->total_amount, $currency) !!}</td>
                    </tr>
                @empty
                    <tr>
                        <td class="report-muted" colspan="2">@lang('pdf_report_no_records')</td>
                    </tr>
                @endforelse
                <tr class="report-total-row">
                    <td class="report-total">@lang('pdf_total_expenses_label')</td>
                    <td class="report-amount report-total">{!! format_money_pdf($totalExpense, $currency) !!}</td>
                </tr>
            </tbody>
        </table>
    </div>
@endsection
