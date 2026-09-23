<?php

namespace App\Adapters\Contacts;

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Contacts\Contracts\CustomerStatsProvider;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Reporting\Queries\CashflowQuery;
use App\Support\ReportingPeriod;
use Carbon\Carbon;

/**
 * One customer's money in numbers: invoiced, spent and received per bucket of
 * the period, plus the totals for the period as a whole. The period and the
 * buckets are the dashboard's, so the two charts always agree.
 *
 * KNOWN QUIRK: only the fiscal-year lookup uses the $companyId that is passed
 * in. Every figure is scoped by whereCompany(), which reads the company header
 * off the current request instead.
 */
class EloquentCustomerStatsProvider implements CustomerStatsProvider
{
    public function __construct(
        private readonly CashflowQuery $cashflowQuery,
    ) {}

    public function get(
        Customer $customer,
        int $companyId,
        bool $previousYear = false,
        ?string $fromDate = null,
        ?string $toDate = null,
    ): array {
        $period = ReportingPeriod::resolve(
            CompanySetting::getSetting('fiscal_year', $companyId),
            Carbon::now(),
            $previousYear,
            $fromDate,
            $toDate,
        );

        $cashflow = $this->cashflowQuery->series($period, $customer->id);

        return [
            'months' => $cashflow['labels'],
            'invoiceTotals' => $cashflow['invoices'],
            'expenseTotals' => $cashflow['expenses'],
            'receiptTotals' => $cashflow['receipts'],
            'netProfit' => $cashflow['total_net_income'],
            'netProfits' => $cashflow['net'],
            'salesTotal' => $cashflow['total_sales'],
            'totalReceipts' => $cashflow['total_receipts'],
            'totalExpenses' => $cashflow['total_expenses'],
            'period' => $cashflow['period'],
        ];
    }
}
