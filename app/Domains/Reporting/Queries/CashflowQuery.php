<?php

namespace App\Domains\Reporting\Queries;

use App\Domains\Purchases\Models\Expense;
use App\Domains\Receivables\Models\Payment;
use App\Domains\Sales\Models\Invoice;
use App\Support\ReportingPeriod;
use Illuminate\Database\Eloquent\Builder;

/**
 * Money invoiced, received and spent over a period, bucket by bucket.
 *
 * Invoiced money is the invoices' base totals, so a credit note's negated total
 * nets its sale back out. Net income is what came in less what went out; money
 * invoiced but not yet received is not part of it.
 *
 * Each series is one query over the whole period, summed into buckets here
 * rather than in SQL, so the count stays at three for any length of period and
 * no date function ties it to one database. A document dated with a time on a
 * bucket's last day lands in that bucket.
 *
 * Every figure is scoped by whereCompany(), which reads the company header off
 * the current request.
 */
class CashflowQuery
{
    /**
     * @return array{
     *     labels: list<string>,
     *     invoices: list<int>,
     *     receipts: list<int>,
     *     expenses: list<int>,
     *     net: list<int>,
     *     total_sales: int,
     *     total_receipts: int,
     *     total_expenses: int,
     *     total_net_income: int,
     *     period: array{from: string, to: string, granularity: string},
     * }
     */
    public function series(ReportingPeriod $period, ?int $customerId = null): array
    {
        $span = [$period->from(), $period->to()];

        $invoices = Invoice::query()
            ->whereBetween('invoice_date', $span)
            ->whereCompany()
            ->when($customerId, fn (Builder $query) => $query->whereCustomer($customerId));

        $payments = Payment::query()
            ->whereBetween('payment_date', $span)
            ->whereCompany()
            ->when($customerId, fn (Builder $query) => $query->whereCustomer($customerId));

        $expenses = Expense::query()
            ->whereBetween('expense_date', $span)
            ->whereCompany()
            ->when($customerId, fn (Builder $query) => $query->whereUser($customerId));

        $invoiceTotals = $this->bucket($period, $invoices, 'invoice_date', 'base_total');
        $receiptTotals = $this->bucket($period, $payments, 'payment_date', 'base_amount');
        $expenseTotals = $this->bucket($period, $expenses, 'expense_date', 'base_amount');

        $totalReceipts = array_sum($receiptTotals);
        $totalExpenses = array_sum($expenseTotals);

        return [
            'labels' => array_column($period->buckets(), 'label'),
            'invoices' => $invoiceTotals,
            'receipts' => $receiptTotals,
            'expenses' => $expenseTotals,
            'net' => array_map(fn (int $in, int $out) => $in - $out, $receiptTotals, $expenseTotals),
            'total_sales' => array_sum($invoiceTotals),
            'total_receipts' => $totalReceipts,
            'total_expenses' => $totalExpenses,
            'total_net_income' => $totalReceipts - $totalExpenses,
            'period' => $period->toArray(),
        ];
    }

    /**
     * Sum one amount column into the period's buckets by one date column.
     *
     * @return list<int>
     */
    private function bucket(ReportingPeriod $period, Builder $query, string $dateColumn, string $amountColumn): array
    {
        $sums = array_fill(0, count($period->buckets()), 0);

        // Plain rows: the models' appended attributes are not needed here
        foreach ($query->toBase()->select([$dateColumn, $amountColumn])->cursor() as $row) {
            $index = $period->bucketIndex(substr((string) $row->{$dateColumn}, 0, 10));

            if ($index !== null) {
                $sums[$index] += (int) $row->{$amountColumn};
            }
        }

        return $sums;
    }
}
