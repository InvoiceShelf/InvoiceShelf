<!DOCTYPE html>
<html lang="en">

<head>
    <title>@lang('pdf_expense_report_label')</title>
    <style type="text/css">
        body {
            font-family: "DejaVu Sans";
        }

        table {
            border-collapse: collapse;
        }

        .sub-container {
            padding: 0px 20px;
        }

        .report-header {
            width: 100%;
        }

        .heading-text {
            font-weight: bold;
            font-size: 24px;
            color: #5851D8;
            width: 100%;
            text-align: left;
            padding: 0px;
            margin: 0px;
        }

        .heading-date-range {
            font-weight: normal;
            font-size: 15px;
            color: #606060;
            width: 100%;
            text-align: right;
            padding: 0px;
            margin: 0px;
        }

        .sub-heading-text {
            font-weight: bold;
            font-size: 16px;
            color: #595959;
            padding: 0px;
            margin: 0px;
            margin-top: 6px;
        }

        .expenses-title {
            margin-top: 30px;
            font-size: 16px;
            line-height: 21px;
            color: #040405;
        }

        .expenses-table-container {
            padding-left: 10px;
        }

        .expenses-table {
            width: 100%;
            padding-bottom: 10px;
        }

        .expense-title {
            padding: 0px;
            margin: 0px;
            font-size: 14px;
            line-height: 21px;
            color: #595959;
        }

        .expense-amount {
            padding: 0px;
            margin: 0px;
            font-size: 14px;
            line-height: 21px;
            text-align: right;
            color: #595959;
        }

        .expense-total-table {
            border-top: 1px solid #EAF1FB;
            width: 100%;
        }

        .expense-total-cell {
            padding-right: 20px;
            padding-top: 10px;
        }

        .expense-total {
            padding-top: 10px;
            padding-right: 30px;
            padding: 0px;
            margin: 0px;
            text-align: right;
            font-weight: bold;
            font-size: 16px;
            line-height: 21px;
            text-align: right;
            color: #040405;
        }

        .report-footer {
            width: 100%;
            margin-top: 40px;
            padding: 15px 20px;
            background: #F9FBFF;
            box-sizing: border-box;
        }

        .report-footer-label {
            padding: 0px;
            margin: 0px;
            text-align: left;
            font-weight: bold;
            font-size: 16px;
            line-height: 21px;
            color: #595959;
        }

        .report-footer-value {
            padding: 0px;
            margin: 0px;
            text-align: right;
            font-weight: bold;
            font-size: 20px;
            line-height: 21px;
            color: #5851D8;
        }
        
        /* -- Items Table -- */

        .items-table {
            margin-top: 35px;
            padding: 0px 30px 10px 30px;
            page-break-before: avoid;
            page-break-after: auto;
        }

        .items-table hr {
            height: 0.1px;
        }

        .item-table-heading-left {
            font-size: 13.5;
            text-align: left;
            color: rgba(0, 0, 0, 0.85);
            padding: 5px;
            padding-bottom: 10px;
        }
        
        .item-table-heading-right {
            font-size: 13.5;
            text-align: right;
            color: rgba(0, 0, 0, 0.85);
            padding: 5px;
            padding-bottom: 10px;
        }

        tr.item-table-heading-row th {
            border-bottom: 0.620315px solid #E8E8E8;
            font-size: 12px;
            line-height: 18px;
        }

        .item-table-heading-row {
            margin-bottom: 10px;
        }

        tr.item-row td {
            font-size: 12px;
            line-height: 18px;
        }

        .item-cell-left {
            font-size: 13;
            color: #040405;
            text-align: left;
            padding: 5px;
            padding-top: 10px;
            border-color: #d9d9d9;
        }
        
        .item-cell-right {
            font-size: 13;
            color: #040405;
            text-align: right;
            padding: 5px;
            padding-top: 10px;
            border-color: #d9d9d9;
        }

        .item-description {
            color: #595959;
            font-size: 9px;
            line-height: 12px;
        }
        
        .item-table-group-total {
            font-size: 14px;
            font-weight: bold;
            text-align: right;
            color: rgba(0, 0, 0, 0.85);
            padding: 5px;
            padding-bottom: 10px;
        }
        
    </style>

    @if (App::isLocale('th'))
    @include('app.pdf.locale.th')
    @endif
</head>

<body>
    <div class="sub-container">
        <table class="report-header">
            <tr>
                <td>
                    <p class="heading-text">{{ $company->name }}</p>
                </td>
                <td>
                    <p class="heading-date-range">{{ $from_date }} - {{ $to_date }}</p>
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <p class="sub-heading-text">@lang('pdf_expense_report_label')</p>
                </td>
            </tr>
        </table>
        <p class="expenses-title">@lang('pdf_expenses_label')</p>
            @foreach ($expenseGroups as $group)
            <p class="expense-title">{{ $group['name'] }}</p>
              <table width="100%" style="margin-bottom:18px;">
                <tr class="item-table-heading-row">
                    <th style="width: 15%;" class="text-left item-table-heading-left">@lang('Date')</th>
                    <th style="width: 70%;" class="text-left item-table-heading-left">@lang('Note')</th>
                    <th style="width: 15%;" class="text-right item-table-heading-right">@lang('Amount')</th>
                </tr>
                @foreach ($group['expenses'] as $expense)
                <tr class="item-row">
                    <td style="width: 15%;" class="text-left item-cell-left">{{ $expense->formatted_expense_date }}</td>
                    <td style="width: 70%;" class="text-left item-cell-left">{{ $expense->notes ? $expense->notes : '-' }}</td>
                    <td style="width: 15%;" class="text-right item-cell-right">{!! format_money_pdf($expense->base_amount, $currency) !!}</td>
                </tr>
                @endforeach

              </table>
               <div class="item-table-group-total">
                <p>@lang('pdf_expense_group_total_label')&nbsp;&nbsp;&nbsp;<span style="color: #5851D8;">{!! format_money_pdf($group['total'], $currency) !!}</span></p>
               </div>
            @endforeach
    </div>
    <table class="report-footer">
        <tr>
            <td>
                <p class="report-footer-label">@lang('pdf_total_expenses_label')</p>
            </td>
            <td>
                <p class="report-footer-value">{!! format_money_pdf($totalExpense, $currency) !!}</p>
            </td>
        </tr>
    </table>
</body>

</html>