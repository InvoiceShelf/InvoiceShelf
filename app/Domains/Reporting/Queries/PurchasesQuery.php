<?php

namespace App\Domains\Reporting\Queries;

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Money\Models\Currency;
use App\Domains\Purchases\Models\Bill;
use App\Domains\Purchases\Models\Expense;
use App\Domains\Purchases\Models\ExpenseCategory;
use App\Domains\Purchases\Models\Supplier;
use App\Domains\Purchases\Models\SupplierCredit;
use App\Domains\Purchases\Models\SupplierPayment;
use App\Domains\Purchases\Models\SupplierRefund;
use App\Support\ProportionalAmount;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

class PurchasesQuery
{
    /**
     * Money that left (or came back to) the company in the period, in the base
     * currency: direct expenses, supplier payments and supplier refunds.
     */
    public function cash(int $companyId, ?string $from = null, ?string $to = null, ?int $supplierId = null): array
    {
        $expenses = $this->cashTotal(Expense::class, 'expense_date', $companyId, $from, $to, $supplierId);
        $paid = $this->cashTotal(SupplierPayment::class, 'payment_date', $companyId, $from, $to, $supplierId);
        $refunds = $this->cashTotal(SupplierRefund::class, 'payment_date', $companyId, $from, $to, $supplierId);

        return [
            'direct_expenses' => $expenses,
            'supplier_payments' => $paid,
            'supplier_refunds' => $refunds,
            'net_cash_out' => $expenses + $paid - $refunds,
        ];
    }

    /**
     * Sum of one cash record type's base amounts, dated within the period.
     * Expenses have no status; payments and refunds count only while open.
     */
    private function cashTotal(
        string $model,
        string $date,
        int $companyId,
        ?string $from,
        ?string $to,
        ?int $supplierId,
    ): int {
        return (int) $model::query()
            ->where('company_id', $companyId)
            ->when($model !== Expense::class, fn ($q) => $q->where('status', 'OPEN'))
            ->when($from, fn ($q) => $q->where($date, '>=', $from))
            ->when($to, fn ($q) => $q->where($date, '<', CarbonImmutable::parse($to)->addDay()->toDateString()))
            ->when($supplierId, fn ($q) => $q->where('supplier_id', $supplierId))
            ->sum('base_amount');
    }

    /**
     * What the company owes its suppliers today, split by how late it is, plus
     * the advances and credits still available to settle it.
     */
    public function payables(int $companyId, ?int $supplierId = null): array
    {
        $timezone = CompanySetting::getSetting('time_zone', $companyId) ?: config('app.timezone');
        $today = CarbonImmutable::now($timezone);

        $bills = Bill::query()
            ->forCompany($companyId)
            ->where('status', 'OPEN')
            ->where('due_amount', '>', 0)
            ->when($supplierId, fn ($q) => $q->where('supplier_id', $supplierId))
            ->get(['id', 'due_date', 'base_due_amount']);

        $summary = [
            'as_of_date' => $today->toDateString(),
            'outstanding' => 0,
            'outstanding_count' => $bills->count(),
            'overdue' => 0,
            'overdue_count' => 0,
            'due_soon' => 0,
            'due_later' => 0,
        ];

        foreach ($bills as $bill) {
            $summary['outstanding'] += $bill->base_due_amount;

            // A bill without a due date is not late, as on the receivables side.
            if ($bill->due_date === null) {
                $bucket = 'due_later';
            } elseif ($bill->due_date < $today->toDateString()) {
                $bucket = 'overdue';
            } elseif ($bill->due_date <= $today->addDays(30)->toDateString()) {
                $bucket = 'due_soon';
            } else {
                $bucket = 'due_later';
            }

            $summary[$bucket] += $bill->base_due_amount;

            if ($bucket === 'overdue') {
                $summary['overdue_count']++;
            }
        }

        $summary['available_advances'] = $this->available(SupplierPayment::class, $companyId, $supplierId);
        $summary['available_credits'] = $this->available(SupplierCredit::class, $companyId, $supplierId);

        return $summary;
    }

    /**
     * Base-currency value of what open payments or credits still have left to
     * allocate, pro rata to their document amount.
     */
    private function available(string $model, int $companyId, ?int $supplierId): int
    {
        return (int) $model::query()
            ->forCompany($companyId)
            ->where('status', 'OPEN')
            ->when($supplierId, fn ($q) => $q->where('supplier_id', $supplierId))
            ->with(['allocations', 'refunds'])
            ->get()
            ->sum(function ($record) use ($model) {
                $amount = $model === SupplierPayment::class ? $record->amount : $record->total;
                $base = $model === SupplierPayment::class ? $record->base_amount : $record->base_total;

                return ProportionalAmount::floor($base, $record->available_amount, $amount);
            });
    }

    /**
     * One supplier's open position per currency: what is due on bills and what
     * advances and credits are still available, in the document currency.
     */
    public function supplierBalances(Supplier $supplier): array
    {
        $balances = [];

        foreach ($supplier->bills()->where('status', 'OPEN')->get() as $bill) {
            $id = $bill->currency_id;
            $balances[$id] ??= ['currency_id' => $id, 'due' => 0, 'advances' => 0, 'credits' => 0];
            $balances[$id]['due'] += $bill->due_amount;
        }

        foreach (['payments' => 'advances', 'credits' => 'credits'] as $relation => $field) {
            $records = $supplier->$relation()
                ->where('status', 'OPEN')
                ->with(['allocations', 'refunds'])
                ->get();

            foreach ($records as $record) {
                $id = $record->currency_id;
                $balances[$id] ??= ['currency_id' => $id, 'due' => 0, 'advances' => 0, 'credits' => 0];
                $balances[$id][$field] += $record->available_amount;
            }
        }

        $currencies = Currency::query()->whereIn('id', array_keys($balances))->get()->keyBy('id');

        return array_values(array_map(
            fn ($row) => [...$row, 'currency' => $currencies->get($row['currency_id'])],
            $balances,
        ));
    }

    /** Document dates determine cost and purchase tax; settlement records are deliberately absent. */
    public function costs(int $companyId, string $from, string $to, ?int $supplierId = null): array
    {
        $categories = [];
        $taxes = [];

        $expenses = Expense::query()
            ->where('company_id', $companyId)
            ->where('expense_date', '>=', $from)
            ->where('expense_date', '<', CarbonImmutable::parse($to)->addDay()->toDateString())
            ->when($supplierId, fn ($q) => $q->where('supplier_id', $supplierId))
            ->with('taxes')
            ->get();

        foreach ($expenses as $expense) {
            $this->addCost(
                $categories,
                $taxes,
                (int) $expense->expense_category_id,
                (int) $expense->base_amount,
                $expense->taxes->toArray(),
                1,
            );
        }

        foreach ([Bill::class => 1, SupplierCredit::class => -1] as $model => $sign) {
            $documents = $model::query()
                ->forCompany($companyId)
                ->where('status', 'OPEN')
                ->whereBetween('document_date', [$from, $to])
                ->when($supplierId, fn ($q) => $q->where('supplier_id', $supplierId))
                ->with('items')
                ->get();

            foreach ($documents as $document) {
                foreach ($document->items as $item) {
                    $this->addCost(
                        $categories,
                        $taxes,
                        (int) $item->expense_category_id,
                        $item->base_total,
                        $item->taxes ?? [],
                        $sign,
                    );
                }
            }
        }

        $names = ExpenseCategory::query()->where('company_id', $companyId)->pluck('name', 'id');

        $categories = array_values(array_map(
            fn ($row) => [...$row, 'name' => $names[$row['expense_category_id']] ?? 'Uncategorized'],
            $categories,
        ));

        return [
            'purchases' => [
                'gross' => array_sum(array_column($categories, 'gross')),
                'tax' => array_sum(array_column($categories, 'tax')),
                'net' => array_sum(array_column($categories, 'net')),
            ],
            'categories' => $categories,
            'taxes' => array_values($taxes),
        ];
    }

    /**
     * Add one cost line to the per-category and per-tax running totals. A
     * negative sign subtracts it, as a supplier credit does.
     */
    private function addCost(
        array &$categories,
        array &$taxes,
        int $categoryId,
        int $gross,
        array $taxRows,
        int $sign,
    ): void {
        $taxTotal = 0;

        foreach ($taxRows as $row) {
            $amount = (int) $row['base_amount'] * $sign;
            $key = $row['tax_type_id'].':'.$row['name'].':'.($row['percent'] ?? '');

            $taxes[$key] ??= [
                'tax_type_id' => $row['tax_type_id'],
                'name' => $row['name'],
                'percent' => $row['percent'] ?? null,
                'amount' => 0,
            ];
            $taxes[$key]['amount'] += $amount;
            $taxTotal += $amount;
        }

        $categories[$categoryId] ??= ['expense_category_id' => $categoryId, 'gross' => 0, 'tax' => 0, 'net' => 0];
        $categories[$categoryId]['gross'] += $gross * $sign;
        $categories[$categoryId]['tax'] += $taxTotal;
        $categories[$categoryId]['net'] += $gross * $sign - $taxTotal;
    }

    /**
     * Everything the purchases report shows for a period: cash movements,
     * costs by category and tax, current payables and the open bills by due date.
     */
    public function report(int $companyId, string $from, string $to, ?int $supplierId = null): array
    {
        $supplier = $supplierId
            ? Supplier::query()->forCompany($companyId)->findOrFail($supplierId)->only(['id', 'name'])
            : null;

        return [
            'supplier' => $supplier,
            'period' => ['from_date' => $from, 'to_date' => $to],
            'currency' => Currency::find(CompanySetting::getSetting('currency', $companyId)),
            'cash' => $this->cash($companyId, $from, $to, $supplierId),
            ...$this->costs($companyId, $from, $to, $supplierId),
            'payables' => $this->payables($companyId, $supplierId),
            'aging' => $this->aging($companyId, $supplierId),
        ];
    }

    /**
     * Open bills with money still owing, earliest due date first.
     */
    private function aging(int $companyId, ?int $supplierId): Collection
    {
        return Bill::query()
            ->forCompany($companyId)
            ->where('status', 'OPEN')
            ->where('due_amount', '>', 0)
            ->when($supplierId, fn ($q) => $q->where('supplier_id', $supplierId))
            ->with(['supplier:id,name', 'currency'])
            ->orderBy('due_date')
            ->get(['id', 'number', 'supplier_id', 'currency_id', 'due_date', 'due_amount', 'base_due_amount']);
    }
}
