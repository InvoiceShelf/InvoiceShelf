<?php

namespace App\Services\Ai\Tools;

use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Ai\Tools\Concerns\ResolvesPeriod;

/**
 * Aggregate stats for the current company over a time window.
 *
 * Use this when the user asks "how much did we make/spend/invoice in <period>".
 * Much cheaper than fetching full invoice lists and summing client-side.
 */
class GetCompanyStatsTool extends AiTool
{
    use ResolvesPeriod;

    /**
     * Bounded periods only — stats over `all_time` is almost always useless
     * (collapses every record into one giant bucket), so we don't offer it
     * here. The ranking tools do expose `all_time` because ranking by totals
     * across the full history is a meaningful question.
     */
    private const PERIODS = [
        'today',
        'this_week',
        'this_month',
        'last_month',
        'this_quarter',
        'this_year',
        'last_year',
    ];

    public function name(): string
    {
        return 'get_company_stats';
    }

    public function description(): string
    {
        return "Aggregate stats for the current company over a named time period: invoice count and total, payment count and total, expense count and total. Use this for 'how much did we earn/spend' questions.";
    }

    public function parameterSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'period' => [
                    'type' => 'string',
                    'enum' => self::PERIODS,
                    'description' => 'Named time window.',
                ],
            ],
            'required' => ['period'],
        ];
    }

    public function requiredAbility(): ?array
    {
        // Cross-entity financial snapshot — gated like the company dashboard.
        return ['dashboard', null];
    }

    public function execute(array $arguments, int $companyId, int $userId): mixed
    {
        $period = (string) ($arguments['period'] ?? 'this_month');
        if (! in_array($period, self::PERIODS, true)) {
            return ['error' => 'invalid_period', 'valid' => self::PERIODS];
        }

        // Stats are always date-scoped (the enum above excludes `all_time`),
        // so rangeFor() is guaranteed to return a non-null pair here.
        [$start, $end] = $this->rangeFor($period);

        // Counts issued invoices only; the total below keeps the credit notes,
        // whose negated amounts are what net the sales figure back out.
        $invoiceCount = Invoice::query()
            ->where('company_id', $companyId)
            ->where('type', Invoice::TYPE_INVOICE)
            ->whereBetween('invoice_date', [$start, $end])
            ->count();

        $invoiceTotal = (float) Invoice::query()
            ->where('company_id', $companyId)
            ->whereBetween('invoice_date', [$start, $end])
            ->sum('total');

        $paymentCount = Payment::query()
            ->where('company_id', $companyId)
            ->whereBetween('payment_date', [$start, $end])
            ->count();

        $paymentTotal = (float) Payment::query()
            ->where('company_id', $companyId)
            ->whereBetween('payment_date', [$start, $end])
            ->sum('amount');

        $expenseCount = Expense::query()
            ->where('company_id', $companyId)
            ->whereBetween('expense_date', [$start, $end])
            ->count();

        $expenseTotal = (float) Expense::query()
            ->where('company_id', $companyId)
            ->whereBetween('expense_date', [$start, $end])
            ->sum('amount');

        return [
            'period' => $period,
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
            'invoices' => ['count' => $invoiceCount, 'total' => $invoiceTotal],
            'payments' => ['count' => $paymentCount, 'total' => $paymentTotal],
            'expenses' => ['count' => $expenseCount, 'total' => $expenseTotal],
        ];
    }
}
