<?php

namespace App\Platform\Mcp\Queries;

use App\Domains\Purchases\Models\Expense;
use App\Domains\Receivables\Models\Payment;
use App\Domains\Reporting\Queries\ReceivablesAgingQuery;
use App\Domains\Sales\Models\Invoice;
use Carbon\CarbonImmutable;

/**
 * A company's money over a window, in its own currency: what it invoiced,
 * received and spent, and what it is owed today.
 *
 * Invoiced is issued invoices less the credit notes issued against them
 * (drafts have not been sent to anyone). Receipts and expenses are as
 * recorded. Everything sums the base_* columns, so documents in other
 * currencies add up at the rate they were recorded at.
 */
class CompanyStatsQuery
{
    public function __construct(
        private readonly ReceivablesAgingQuery $receivables,
    ) {}

    /**
     * @return array{
     *     invoiced: int, invoice_count: int, credited: int, received: int, payment_count: int,
     *     expenses: int, expense_count: int, net_income: int,
     *     receivables: array{outstanding: int, outstanding_count: int, overdue: int, overdue_count: int, due_soon: int, due_later: int},
     * }
     */
    public function totals(int $companyId, ?string $from, ?string $to, CarbonImmutable $today): array
    {
        $issued = Window::apply(
            Invoice::query()->where('company_id', $companyId)->where('status', '!=', Invoice::STATUS_DRAFT),
            'invoice_date', $from, $to,
        );

        $payments = Window::apply(Payment::query()->where('company_id', $companyId), 'payment_date', $from, $to);
        $expenses = Window::apply(Expense::query()->where('company_id', $companyId), 'expense_date', $from, $to);

        $invoiced = (int) $issued->clone()->where('type', Invoice::TYPE_INVOICE)->sum('base_total');
        $credited = (int) -$issued->clone()->where('type', Invoice::TYPE_CREDIT_NOTE)->sum('base_total');
        $received = (int) $payments->clone()->sum('base_amount');
        $spent = (int) $expenses->clone()->sum('base_amount');

        return [
            'invoiced' => $invoiced - $credited,
            'invoice_count' => $issued->clone()->where('type', Invoice::TYPE_INVOICE)->count(),
            'credited' => $credited,
            'received' => $received,
            'payment_count' => $payments->count(),
            'expenses' => $spent,
            'expense_count' => $expenses->count(),
            'net_income' => $received - $spent,
            'receivables' => $this->receivables->summary($companyId, $today),
        ];
    }
}
