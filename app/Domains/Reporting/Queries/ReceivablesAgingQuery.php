<?php

namespace App\Domains\Reporting\Queries;

use App\Domains\Sales\Models\Invoice;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * What a company's customers owe it, split by how late the money is.
 *
 * Only issued invoices count: a draft has not been sent to anyone, and a credit
 * note never carries a due amount of its own. The figures are the invoices'
 * remaining balances in the company's base currency, so a partly paid invoice
 * counts what is still owed and invoices in other currencies add up.
 */
class ReceivablesAgingQuery
{
    /**
     * How far ahead an invoice counts as falling due soon, in days.
     */
    public const DUE_SOON_DAYS = 30;

    /**
     * Overdue (the due date has passed), due soon (due today or within
     * DUE_SOON_DAYS), and due later (beyond that, or with no due date).
     *
     * @return array{outstanding: int, outstanding_count: int, overdue: int, overdue_count: int, due_soon: int, due_later: int}
     */
    public function summary(int|string $companyId, CarbonInterface $today): array
    {
        $todayDate = $today->toDateString();
        $soonDate = $today->copy()->addDays(self::DUE_SOON_DAYS)->toDateString();

        $overdue = $this->receivables($companyId)->where('due_date', '<', $todayDate);

        $dueSoon = $this->receivables($companyId)
            ->where('due_date', '>=', $todayDate)
            ->where('due_date', '<=', $soonDate);

        $dueLater = $this->receivables($companyId)->where(function (Builder $query) use ($soonDate) {
            $query->whereNull('due_date')->orWhere('due_date', '>', $soonDate);
        });

        $overdueAmount = (int) $overdue->clone()->sum('base_due_amount');
        $dueSoonAmount = (int) $dueSoon->sum('base_due_amount');
        $dueLaterAmount = (int) $dueLater->sum('base_due_amount');

        return [
            'outstanding' => $overdueAmount + $dueSoonAmount + $dueLaterAmount,
            'outstanding_count' => $this->receivables($companyId)->count(),
            'overdue' => $overdueAmount,
            'overdue_count' => $overdue->count(),
            'due_soon' => $dueSoonAmount,
            'due_later' => $dueLaterAmount,
        ];
    }

    /**
     * Issued invoices of one company that still have money owing.
     */
    private function receivables(int|string $companyId): Builder
    {
        return Invoice::query()
            ->whereCompanyId($companyId)
            ->where('type', Invoice::TYPE_INVOICE)
            ->where('status', '!=', Invoice::STATUS_DRAFT)
            ->where('base_due_amount', '>', 0);
    }
}
