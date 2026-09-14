<?php

namespace App\Platform\Modules\Infrastructure;

use App\Domains\Accounts\Models\User;
use App\Domains\Catalog\Models\Item;
use App\Domains\Contacts\Models\Address;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Purchases\Models\Expense;
use App\Domains\Purchases\Models\ExpenseCategory;
use App\Domains\Receivables\Models\Payment;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\InvoiceItem;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use InvoiceShelf\Modules\Contracts\Host\CompanyDataReader;

class EloquentCompanyDataReader implements CompanyDataReader
{
    public function companyStats(int $companyId, string $startDate, string $endDate): array
    {
        return [
            'invoices' => [
                'count' => Invoice::query()->where('company_id', $companyId)->where('type', Invoice::TYPE_INVOICE)->whereBetween('invoice_date', [$startDate, $endDate])->count(),
                'total' => (float) Invoice::query()->where('company_id', $companyId)->whereBetween('invoice_date', [$startDate, $endDate])->sum('total'),
            ],
            'payments' => [
                'count' => Payment::query()->where('company_id', $companyId)->whereBetween('payment_date', [$startDate, $endDate])->count(),
                'total' => (float) Payment::query()->where('company_id', $companyId)->whereBetween('payment_date', [$startDate, $endDate])->sum('amount'),
            ],
            'expenses' => [
                'count' => Expense::query()->where('company_id', $companyId)->whereBetween('expense_date', [$startDate, $endDate])->count(),
                'total' => (float) Expense::query()->where('company_id', $companyId)->whereBetween('expense_date', [$startDate, $endDate])->sum('amount'),
            ],
        ];
    }

    public function findCustomer(int $companyId, int $customerId): ?array
    {
        $customer = Customer::query()
            ->where('company_id', $companyId)
            ->whereKey($customerId)
            ->with(['billingAddress', 'shippingAddress', 'currency'])
            ->first();

        if ($customer === null) {
            return null;
        }

        return [
            'id' => $customer->id,
            'name' => $customer->name,
            'display_name' => $customer->display_name,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'contact_name' => $customer->contact_name,
            'company_name' => $customer->company_name,
            'website' => $customer->website,
            'enable_portal' => (bool) $customer->enable_portal,
            'currency_id' => $customer->currency_id,
            'currency' => $customer->currency ? [
                'id' => $customer->currency->id,
                'code' => $customer->currency->code,
                'symbol' => $customer->currency->symbol,
                'precision' => $customer->currency->precision,
            ] : null,
            'billing_address' => $this->address($customer->billingAddress),
            'shipping_address' => $this->address($customer->shippingAddress),
            'totals' => [
                'invoice_count' => Invoice::query()->where('company_id', $companyId)->where('customer_id', $customer->id)->where('type', Invoice::TYPE_INVOICE)->count(),
                'outstanding_amount' => (float) Invoice::query()->where('company_id', $companyId)->where('customer_id', $customer->id)->whereIn('paid_status', ['UNPAID', 'PARTIALLY_PAID'])->sum('due_amount'),
            ],
        ];
    }

    public function searchCustomers(int $companyId, ?string $query, int $limit): array
    {
        $customers = Customer::query()->where('company_id', $companyId)->orderBy('name')->limit($limit);

        if ($query !== null && $query !== '') {
            $customers->where(function ($builder) use ($query) {
                $builder->where('name', 'like', "%{$query}%")
                    ->orWhere('display_name', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%")
                    ->orWhere('company_name', 'like', "%{$query}%")
                    ->orWhere('contact_name', 'like', "%{$query}%");
            });
        }

        return $customers->get()->map(fn (Customer $customer): array => [
            'id' => $customer->id,
            'name' => $customer->name,
            'display_name' => $customer->display_name,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'company_name' => $customer->company_name,
            'currency_id' => $customer->currency_id,
        ])->all();
    }

    public function rankCustomers(int $companyId, string $metric, ?string $startDate, ?string $endDate, int $limit): array
    {
        $rows = match ($metric) {
            'invoiced_total' => $this->customerInvoiceRanking($companyId, $startDate, $endDate, $limit, 'SUM(total)'),
            'paid_total' => $this->customerPaymentRanking($companyId, $startDate, $endDate, $limit),
            'invoice_count' => $this->customerInvoiceRanking($companyId, $startDate, $endDate, $limit, 'COUNT(*)'),
            'outstanding_balance' => Invoice::query()->where('company_id', $companyId)->whereNotNull('customer_id')->whereIn('paid_status', ['UNPAID', 'PARTIALLY_PAID'])->selectRaw('customer_id, SUM(due_amount) as metric_value, COUNT(*) as invoice_count')->groupBy('customer_id')->orderByDesc('metric_value')->limit($limit)->get(),
        };

        $customers = Customer::query()->where('company_id', $companyId)->whereIn('id', $rows->pluck('customer_id'))->get()->keyBy('id');

        return $rows->map(function ($row) use ($customers, $metric): array {
            $customer = $customers->get($row->customer_id);

            return [
                'customer_id' => (int) $row->customer_id,
                'name' => $customer?->name,
                'display_name' => $customer?->display_name,
                'company_name' => $customer?->company_name,
                'metric_value' => $metric === 'invoice_count' ? (int) $row->metric_value : (float) $row->metric_value,
                'invoice_count' => isset($row->invoice_count) ? (int) $row->invoice_count : null,
            ];
        })->all();
    }

    public function findInvoice(int $companyId, string $invoiceNumber): ?array
    {
        $invoice = Invoice::query()->where('company_id', $companyId)->where('invoice_number', $invoiceNumber)->with(['customer:id,name,email,phone', 'items', 'taxes'])->first();

        if ($invoice === null) {
            return null;
        }

        return [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'reference_number' => $invoice->reference_number,
            'status' => $invoice->status,
            'paid_status' => $invoice->paid_status,
            'invoice_date' => $this->date($invoice->invoice_date),
            'due_date' => $this->date($invoice->due_date),
            'sub_total' => $invoice->sub_total,
            'tax' => $invoice->tax,
            'discount' => $invoice->discount,
            'total' => $invoice->total,
            'due_amount' => $invoice->due_amount,
            'overdue' => (bool) $invoice->overdue,
            'notes' => $invoice->notes,
            'customer' => $invoice->customer ? ['id' => $invoice->customer->id, 'name' => $invoice->customer->name, 'email' => $invoice->customer->email, 'phone' => $invoice->customer->phone] : null,
            'items' => $invoice->items->map(fn ($item): array => ['name' => $item->name, 'description' => $item->description, 'quantity' => $item->quantity, 'price' => $item->price, 'total' => $item->total])->all(),
            'taxes' => $invoice->taxes->map(fn ($tax): array => ['name' => $tax->name, 'percent' => $tax->percent, 'amount' => $tax->amount])->all(),
        ];
    }

    public function searchInvoices(int $companyId, ?string $query, ?string $status, ?int $customerId, int $limit): array
    {
        $invoices = Invoice::query()->where('company_id', $companyId)->with('customer:id,name')->latest('invoice_date')->limit($limit);

        if ($query !== null && $query !== '') {
            $invoices->where(fn ($builder) => $builder->where('invoice_number', 'like', "%{$query}%")->orWhere('reference_number', 'like', "%{$query}%"));
        }

        if ($status !== null && $status !== '') {
            $status = strtoupper($status);
            if (in_array($status, ['PAID', 'UNPAID', 'PARTIALLY_PAID'], true)) {
                $invoices->where('paid_status', $status);
            } elseif ($status === 'OVERDUE') {
                $invoices->where('overdue', true);
            } else {
                $invoices->where('status', $status);
            }
        }

        if ($customerId !== null) {
            $invoices->where('customer_id', $customerId);
        }

        return $invoices->get()->map(fn (Invoice $invoice): array => $this->invoiceSummary($invoice))->all();
    }

    public function overdueInvoices(int $companyId, int $limit): array
    {
        return Invoice::query()->where('company_id', $companyId)->where('overdue', true)->with('customer:id,name')->orderBy('due_date')->limit($limit)->get()->map(fn (Invoice $invoice): array => $this->invoiceSummary($invoice))->all();
    }

    public function recentPayments(int $companyId, string $startDate, int $limit): array
    {
        return Payment::query()->where('company_id', $companyId)->where('payment_date', '>=', $startDate)->with(['allocations:id,payment_id,invoice_id,amount', 'customer:id,name', 'paymentMethod:id,name'])->latest('payment_date')->limit($limit)->get()->map(function (Payment $payment): array {
            $allocated = (int) $payment->allocations->sum('amount');

            return [
                'id' => $payment->id,
                'payment_number' => $payment->payment_number,
                'payment_date' => $this->date($payment->payment_date),
                'amount' => $payment->amount,
                'customer_id' => $payment->customer_id,
                'customer_name' => $payment->customer?->name,
                'allocations' => $payment->allocations->map(fn ($allocation): array => ['invoice_id' => $allocation->invoice_id, 'amount' => $allocation->amount])->all(),
                'allocated_amount' => $allocated,
                'unallocated_amount' => (int) $payment->amount - $allocated,
                'payment_method' => $payment->paymentMethod?->name,
            ];
        })->all();
    }

    public function expenseCategories(int $companyId): array
    {
        return ExpenseCategory::query()->where('company_id', $companyId)->orderBy('name')->get(['id', 'name', 'description'])->map(fn (ExpenseCategory $category): array => ['id' => $category->id, 'name' => $category->name, 'description' => $category->description])->all();
    }

    public function rankExpenseCategories(int $companyId, ?string $startDate, ?string $endDate, int $limit): array
    {
        $expenses = Expense::query()->where('company_id', $companyId)->whereNotNull('expense_category_id')->selectRaw('expense_category_id, SUM(amount) as total_amount, COUNT(*) as expense_count')->groupBy('expense_category_id')->orderByDesc('total_amount')->limit($limit);

        if ($startDate !== null && $endDate !== null) {
            $expenses->whereBetween('expense_date', [$startDate, $endDate]);
        }

        $rows = $expenses->get();
        $categories = ExpenseCategory::query()->where('company_id', $companyId)->whereIn('id', $rows->pluck('expense_category_id'))->get()->keyBy('id');

        return $rows->map(fn ($row): array => ['expense_category_id' => (int) $row->expense_category_id, 'name' => $categories->get($row->expense_category_id)?->name, 'total_amount' => (float) $row->total_amount, 'expense_count' => (int) $row->expense_count])->all();
    }

    public function searchItems(int $companyId, ?string $query, int $limit): array
    {
        $items = Item::query()->where('company_id', $companyId)->orderBy('name')->limit($limit);

        if ($query !== null && $query !== '') {
            $items->where(fn ($builder) => $builder->where('name', 'like', "%{$query}%")->orWhere('description', 'like', "%{$query}%"));
        }

        return $items->get()->map(fn (Item $item): array => ['id' => $item->id, 'name' => $item->name, 'description' => $item->description, 'price' => $item->price])->all();
    }

    public function rankItems(int $companyId, string $metric, ?string $startDate, ?string $endDate, int $limit): array
    {
        $items = InvoiceItem::query()->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')->where('invoices.company_id', $companyId)->whereNotNull('invoice_items.item_id')->selectRaw('invoice_items.item_id, SUM(invoice_items.quantity) as total_quantity, SUM(invoice_items.total) as total_revenue')->groupBy('invoice_items.item_id')->orderByDesc($metric === 'revenue' ? 'total_revenue' : 'total_quantity')->limit($limit);

        if ($startDate !== null && $endDate !== null) {
            $items->whereBetween('invoices.invoice_date', [$startDate, $endDate]);
        }

        $rows = $items->get();
        $catalog = Item::query()->where('company_id', $companyId)->whereIn('id', $rows->pluck('item_id'))->get()->keyBy('id');

        return $rows->map(fn ($row): array => ['item_id' => (int) $row->item_id, 'name' => $catalog->get($row->item_id)?->name, 'quantity_sold' => (float) $row->total_quantity, 'revenue' => (float) $row->total_revenue])->all();
    }

    public function companyMembers(int $companyId): array
    {
        return User::query()
            ->whereHas('companies', fn ($query) => $query->whereKey($companyId))
            ->with('media')
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar ?: null,
            ])
            ->all();
    }

    public function existingInvoiceIds(int $companyId, array $invoiceIds): array
    {
        $ids = array_values(array_map('intval', $invoiceIds));

        if ($ids === []) {
            return [];
        }

        return Invoice::query()
            ->where('company_id', $companyId)
            ->whereIn('id', $ids)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    private function customerInvoiceRanking(int $companyId, ?string $startDate, ?string $endDate, int $limit, string $aggregate): Collection
    {
        $invoices = Invoice::query()->where('company_id', $companyId)->whereNotNull('customer_id')->selectRaw("customer_id, {$aggregate} as metric_value, COUNT(*) as invoice_count")->groupBy('customer_id')->orderByDesc('metric_value')->limit($limit);

        if ($startDate !== null && $endDate !== null) {
            $invoices->whereBetween('invoice_date', [$startDate, $endDate]);
        }

        return $invoices->get();
    }

    private function customerPaymentRanking(int $companyId, ?string $startDate, ?string $endDate, int $limit): Collection
    {
        $payments = Payment::query()->where('company_id', $companyId)->whereNotNull('customer_id')->selectRaw('customer_id, SUM(amount) as metric_value')->groupBy('customer_id')->orderByDesc('metric_value')->limit($limit);

        if ($startDate !== null && $endDate !== null) {
            $payments->whereBetween('payment_date', [$startDate, $endDate]);
        }

        return $payments->get();
    }

    private function invoiceSummary(Invoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'customer_id' => $invoice->customer_id,
            'customer_name' => $invoice->customer?->name,
            'invoice_date' => $this->date($invoice->invoice_date),
            'due_date' => $this->date($invoice->due_date),
            'status' => $invoice->status,
            'paid_status' => $invoice->paid_status,
            'total' => $invoice->total,
            'due_amount' => $invoice->due_amount,
            'overdue' => (bool) $invoice->overdue,
        ];
    }

    private function address(?Address $address): ?array
    {
        if ($address === null) {
            return null;
        }

        return $address->only(['id', 'name', 'address_street_1', 'address_street_2', 'city', 'state', 'country_id', 'zip', 'phone', 'fax', 'type']);
    }

    private function date(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $value instanceof Carbon ? $value->toDateString() : substr((string) $value, 0, 10);
    }
}
