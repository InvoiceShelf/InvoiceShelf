<?php

namespace App\Platform\Mcp\Queries;

use App\Domains\Contacts\Models\Customer;
use App\Domains\Purchases\Models\Expense;
use App\Domains\Purchases\Models\ExpenseCategory;
use App\Domains\Receivables\Models\Payment;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\InvoiceItem;

/**
 * Customers, items and expense categories ranked by money, in the company's
 * currency (the base_* columns). Only issued invoices count.
 */
class RankingQuery
{
    public const CUSTOMER_METRICS = ['invoiced_total', 'paid_total', 'invoice_count', 'outstanding_balance'];

    public const ITEM_METRICS = ['revenue', 'quantity'];

    /**
     * @return list<array{customer_id: int, name: string|null, value: int}>
     */
    public function customers(int $companyId, string $metric, ?string $from, ?string $to, int $limit): array
    {
        $query = match ($metric) {
            'paid_total' => Window::apply(Payment::query()->where('company_id', $companyId), 'payment_date', $from, $to)
                ->selectRaw('customer_id, SUM(base_amount) as value'),
            'outstanding_balance' => $this->issued($companyId)
                ->where('type', Invoice::TYPE_INVOICE)
                ->where('base_due_amount', '>', 0)
                ->selectRaw('customer_id, SUM(base_due_amount) as value'),
            'invoice_count' => Window::apply($this->issued($companyId)->where('type', Invoice::TYPE_INVOICE), 'invoice_date', $from, $to)
                ->selectRaw('customer_id, COUNT(*) as value'),
            default => Window::apply($this->issued($companyId), 'invoice_date', $from, $to)
                ->selectRaw('customer_id, SUM(base_total) as value'),
        };

        $rows = $query->toBase()->groupBy('customer_id')->orderByDesc('value')->limit($limit)->get();
        $names = Customer::query()->whereIn('id', $rows->pluck('customer_id'))->pluck('name', 'id');

        return $rows->map(fn (object $row) => [
            'customer_id' => (int) $row->customer_id,
            'name' => $names[$row->customer_id] ?? null,
            'value' => (int) $row->value,
        ])->all();
    }

    /**
     * Invoice lines grouped by catalogue item, or by line name for lines
     * typed in by hand.
     *
     * @return list<array{item_id: int|null, name: string, revenue: int, quantity: float}>
     */
    public function items(int $companyId, string $metric, ?string $from, ?string $to, int $limit): array
    {
        $lines = InvoiceItem::query()
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->where('invoices.company_id', $companyId)
            ->where('invoices.type', Invoice::TYPE_INVOICE)
            ->where('invoices.status', '!=', Invoice::STATUS_DRAFT);

        Window::apply($lines, 'invoices.invoice_date', $from, $to);

        $groups = [];

        foreach ($lines->toBase()->groupBy('invoice_items.item_id', 'invoice_items.name')
            ->selectRaw('invoice_items.item_id, invoice_items.name, SUM(invoice_items.base_total) as revenue, SUM(invoice_items.quantity) as quantity')
            ->get() as $row) {
            $key = $row->item_id ? "item:{$row->item_id}" : 'name:'.mb_strtolower(trim((string) $row->name));
            $groups[$key] ??= ['item_id' => $row->item_id ? (int) $row->item_id : null, 'name' => (string) $row->name, 'revenue' => 0, 'quantity' => 0.0];
            $groups[$key]['revenue'] += (int) $row->revenue;
            $groups[$key]['quantity'] += (float) $row->quantity;
        }

        $sortKey = $metric === 'quantity' ? 'quantity' : 'revenue';
        usort($groups, fn (array $a, array $b) => $b[$sortKey] <=> $a[$sortKey]);

        return array_slice(array_values($groups), 0, $limit);
    }

    /**
     * @return list<array{category_id: int|null, name: string|null, total: int, count: int}>
     */
    public function expenseCategories(int $companyId, ?string $from, ?string $to, int $limit): array
    {
        $rows = Window::apply(Expense::query()->where('company_id', $companyId), 'expense_date', $from, $to)
            ->toBase()
            ->groupBy('expense_category_id')
            ->selectRaw('expense_category_id, SUM(base_amount) as total, COUNT(*) as count')
            ->orderByDesc('total')
            ->limit($limit)
            ->get();

        $names = ExpenseCategory::query()->whereIn('id', $rows->pluck('expense_category_id')->filter())->pluck('name', 'id');

        return $rows->map(fn (object $row) => [
            'category_id' => $row->expense_category_id ? (int) $row->expense_category_id : null,
            'name' => $row->expense_category_id ? ($names[$row->expense_category_id] ?? null) : null,
            'total' => (int) $row->total,
            'count' => (int) $row->count,
        ])->all();
    }

    private function issued(int $companyId)
    {
        return Invoice::query()->where('company_id', $companyId)->where('status', '!=', Invoice::STATUS_DRAFT);
    }
}
