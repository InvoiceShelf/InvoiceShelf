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

class PurchasesQuery
{
    public function cash(int $companyId, ?string $from = null, ?string $to = null, ?int $supplierId = null): array
    {
        $total = function (string $model, string $date) use ($companyId, $from, $to, $supplierId): int {
            return (int) $model::query()->where('company_id', $companyId)
                ->when($model !== Expense::class, fn ($q) => $q->where('status', 'OPEN'))
                ->when($from, fn ($q) => $q->where($date, '>=', $from))
                ->when($to, fn ($q) => $q->where($date, '<=', $to))
                ->when($supplierId, fn ($q) => $q->where('supplier_id', $supplierId))->sum('base_amount');
        };
        $expenses = $total(Expense::class, 'expense_date');
        $paid = $total(SupplierPayment::class, 'payment_date');
        $refunds = $total(SupplierRefund::class, 'payment_date');

        return ['direct_expenses' => $expenses, 'supplier_payments' => $paid, 'supplier_refunds' => $refunds, 'net_cash_out' => $expenses + $paid - $refunds];
    }

    public function payables(int $companyId, ?int $supplierId = null): array
    {
        $today = CarbonImmutable::now(CompanySetting::getSetting('time_zone', $companyId) ?: config('app.timezone'));
        $bills = Bill::query()->forCompany($companyId)->where('status', 'OPEN')->where('due_amount', '>', 0)
            ->when($supplierId, fn ($q) => $q->where('supplier_id', $supplierId))->get(['id', 'due_date', 'base_due_amount']);
        $summary = ['outstanding' => 0, 'outstanding_count' => $bills->count(), 'overdue' => 0, 'overdue_count' => 0, 'due_soon' => 0, 'due_later' => 0];
        foreach ($bills as $bill) {
            $summary['outstanding'] += $bill->base_due_amount;
            $bucket = $bill->due_date < $today->toDateString() ? 'overdue' : ($bill->due_date <= $today->addDays(30)->toDateString() ? 'due_soon' : 'due_later');
            $summary[$bucket] += $bill->base_due_amount;
            if ($bucket === 'overdue') {
                $summary['overdue_count']++;
            }
        }
        $summary['available_advances'] = $this->available(SupplierPayment::class, $companyId, $supplierId);
        $summary['available_credits'] = $this->available(SupplierCredit::class, $companyId, $supplierId);

        return $summary;
    }

    private function available(string $model, int $companyId, ?int $supplierId): int
    {
        return (int) $model::query()->forCompany($companyId)->where('status', 'OPEN')->when($supplierId, fn ($q) => $q->where('supplier_id', $supplierId))->with(['allocations', 'refunds'])->get()->sum(function ($record) use ($model) {
            $amount = $model === SupplierPayment::class ? $record->amount : $record->total;
            $base = $model === SupplierPayment::class ? $record->base_amount : $record->base_total;

            return ProportionalAmount::floor($base, $record->available_amount, $amount);
        });
    }

    public function supplierBalances(Supplier $supplier): array
    {
        $balances = [];
        foreach ($supplier->bills()->where('status', 'OPEN')->get() as $bill) {
            $id = $bill->currency_id;
            $balances[$id] ??= ['currency_id' => $id, 'due' => 0, 'advances' => 0, 'credits' => 0];
            $balances[$id]['due'] += $bill->due_amount;
        }
        foreach (['payments' => 'advances', 'credits' => 'credits'] as $relation => $field) {
            foreach ($supplier->$relation()->where('status', 'OPEN')->with(['allocations', 'refunds'])->get() as $record) {
                $id = $record->currency_id;
                $balances[$id] ??= ['currency_id' => $id, 'due' => 0, 'advances' => 0, 'credits' => 0];
                $balances[$id][$field] += $record->available_amount;
            }
        }
        $currencies = Currency::query()->whereIn('id', array_keys($balances))->get()->keyBy('id');

        return array_values(array_map(fn ($row) => [...$row, 'currency' => $currencies->get($row['currency_id'])], $balances));
    }

    /** Document dates determine cost and purchase tax; settlement records are deliberately absent. */
    public function report(int $companyId, string $from, string $to, ?int $supplierId = null): array
    {
        $categories = [];
        $taxes = [];
        $add = function (int $categoryId, int $gross, array $taxRows, int $sign) use (&$categories, &$taxes): void {
            $taxTotal = 0;
            foreach ($taxRows as $row) {
                $amount = (int) $row['base_amount'] * $sign;
                $key = $row['tax_type_id'].':'.$row['name'].':'.($row['percent'] ?? '');
                $taxes[$key] ??= ['tax_type_id' => $row['tax_type_id'], 'name' => $row['name'], 'percent' => $row['percent'] ?? null, 'amount' => 0];
                $taxes[$key]['amount'] += $amount;
                $taxTotal += $amount;
            }
            $categories[$categoryId] ??= ['expense_category_id' => $categoryId, 'gross' => 0, 'tax' => 0, 'net' => 0];
            $categories[$categoryId]['gross'] += $gross * $sign;
            $categories[$categoryId]['tax'] += $taxTotal;
            $categories[$categoryId]['net'] += $gross * $sign - $taxTotal;
        };
        $expenses = Expense::query()->where('company_id', $companyId)->whereBetween('expense_date', [$from, $to])->when($supplierId, fn ($q) => $q->where('supplier_id', $supplierId))->with('taxes')->get();
        foreach ($expenses as $expense) {
            $add((int) $expense->expense_category_id, (int) $expense->base_amount, $expense->taxes->toArray(), 1);
        }
        foreach ([Bill::class => 1, SupplierCredit::class => -1] as $model => $sign) {
            $documents = $model::query()->forCompany($companyId)->where('status', 'OPEN')->whereBetween('document_date', [$from, $to])->when($supplierId, fn ($q) => $q->where('supplier_id', $supplierId))->with('items')->get();
            foreach ($documents as $document) {
                foreach ($document->items as $item) {
                    $add((int) $item->expense_category_id, $item->base_total, $item->taxes ?? [], $sign);
                }
            }
        }
        $names = ExpenseCategory::query()->where('company_id', $companyId)->pluck('name', 'id');
        $categories = array_values(array_map(fn ($row) => [...$row, 'name' => $names[$row['expense_category_id']] ?? 'Uncategorized'], $categories));

        return [
            'period' => ['from_date' => $from, 'to_date' => $to],
            'currency' => Currency::find(CompanySetting::getSetting('currency', $companyId)),
            'cash' => $this->cash($companyId, $from, $to, $supplierId),
            'purchases' => ['gross' => array_sum(array_column($categories, 'gross')), 'tax' => array_sum(array_column($categories, 'tax')), 'net' => array_sum(array_column($categories, 'net'))],
            'categories' => $categories, 'taxes' => array_values($taxes), 'payables' => $this->payables($companyId, $supplierId),
            'aging' => Bill::query()->forCompany($companyId)->where('status', 'OPEN')->where('due_amount', '>', 0)->when($supplierId, fn ($q) => $q->where('supplier_id', $supplierId))->with(['supplier:id,name', 'currency'])->orderBy('due_date')->get(['id', 'number', 'supplier_id', 'currency_id', 'due_date', 'due_amount', 'base_due_amount']),
        ];
    }
}
