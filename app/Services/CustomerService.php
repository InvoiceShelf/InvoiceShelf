<?php

namespace App\Services;

use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CustomerService
{
    public function create(Request $request): Customer
    {
        $customer = Customer::create($request->getCustomerPayload());

        if ($request->shipping) {
            if ($request->hasAddress($request->shipping)) {
                $customer->addresses()->create($request->getShippingAddress());
            }
        }

        if ($request->billing) {
            if ($request->hasAddress($request->billing)) {
                $customer->addresses()->create($request->getBillingAddress());
            }
        }

        $customFields = $request->customFields;

        if ($customFields) {
            $customer->addCustomFields($customFields);
        }

        return Customer::with('billingAddress', 'shippingAddress', 'fields')->find($customer->id);
    }

    /**
     * @throws ValidationException
     */
    public function update(Request $request, Customer $customer): Customer
    {
        $condition = $customer->estimates()->exists() || $customer->invoices()->exists() || $customer->payments()->exists() || $customer->recurringInvoices()->exists();

        if (($customer->currency_id !== $request->currency_id) && $condition) {
            throw ValidationException::withMessages([
                'currency_id' => ['you_cannot_edit_currency'],
            ]);
        }

        $customer->update($request->getCustomerPayload());

        $customer->addresses()->delete();

        if ($request->shipping) {
            if ($request->hasAddress($request->shipping)) {
                $customer->addresses()->create($request->getShippingAddress());
            }
        }

        if ($request->billing) {
            if ($request->hasAddress($request->billing)) {
                $customer->addresses()->create($request->getBillingAddress());
            }
        }

        $customFields = $request->customFields;

        if ($customFields) {
            $customer->updateCustomFields($customFields);
        }

        return Customer::with('billingAddress', 'shippingAddress', 'fields')->find($customer->id);
    }

    public function delete(Collection $ids): bool
    {
        foreach ($ids as $id) {
            $customer = Customer::find($id);

            if ($customer->estimates()->exists()) {
                $customer->estimates()->delete();
            }

            if ($customer->invoices()->exists()) {
                $customer->invoices->map(function ($invoice) {
                    if ($invoice->transactions()->exists()) {
                        $invoice->transactions()->delete();
                    }
                    $invoice->delete();
                });
            }

            if ($customer->payments()->exists()) {
                $customer->payments()->delete();
            }

            if ($customer->addresses()->exists()) {
                $customer->addresses()->delete();
            }

            if ($customer->expenses()->exists()) {
                $customer->expenses()->delete();
            }

            if ($customer->recurringInvoices()->exists()) {
                foreach ($customer->recurringInvoices as $recurringInvoice) {
                    if ($recurringInvoice->items()->exists()) {
                        $recurringInvoice->items()->delete();
                    }

                    $recurringInvoice->delete();
                }
            }

            $customer->delete();
        }

        return true;
    }

    public function getStats(Customer $customer, int $companyId, bool $previousYear = false): array
    {
        $i = 0;
        $months = [];
        $invoiceTotals = [];
        $expenseTotals = [];
        $receiptTotals = [];
        $netProfits = [];
        $monthCounter = 0;
        $fiscalYear = CompanySetting::getSetting('fiscal_year', $companyId);
        $startDate = Carbon::now();
        $start = Carbon::now();
        $end = Carbon::now();
        $terms = explode('-', $fiscalYear);
        $companyStartMonth = intval($terms[0]);

        if ($companyStartMonth <= $start->month) {
            $startDate->month($companyStartMonth)->startOfMonth();
            $start->month($companyStartMonth)->startOfMonth();
            $end->month($companyStartMonth)->endOfMonth();
        } else {
            $startDate->subYear()->month($companyStartMonth)->startOfMonth();
            $start->subYear()->month($companyStartMonth)->startOfMonth();
            $end->subYear()->month($companyStartMonth)->endOfMonth();
        }

        if ($previousYear) {
            $startDate->subYear()->startOfMonth();
            $start->subYear()->startOfMonth();
            $end->subYear()->endOfMonth();
        }

        while ($monthCounter < 12) {
            array_push(
                $invoiceTotals,
                Invoice::whereBetween(
                    'invoice_date',
                    [$start->format('Y-m-d'), $end->format('Y-m-d')]
                )
                    ->whereCompany()
                    ->whereCustomer($customer->id)
                    ->sum('base_total') ?? 0
            );
            array_push(
                $expenseTotals,
                Expense::whereBetween(
                    'expense_date',
                    [$start->format('Y-m-d'), $end->format('Y-m-d')]
                )
                    ->whereCompany()
                    ->whereUser($customer->id)
                    ->sum('base_amount') ?? 0
            );
            array_push(
                $receiptTotals,
                Payment::whereBetween(
                    'payment_date',
                    [$start->format('Y-m-d'), $end->format('Y-m-d')]
                )
                    ->whereCompany()
                    ->whereCustomer($customer->id)
                    ->sum('base_amount') ?? 0
            );
            array_push(
                $netProfits,
                ($receiptTotals[$i] - $expenseTotals[$i])
            );
            $i++;
            array_push($months, $start->translatedFormat('M'));
            $monthCounter++;
            $end->startOfMonth();
            $start->addMonth()->startOfMonth();
            $end->addMonth()->endOfMonth();
        }

        $start->subMonth()->endOfMonth();

        $salesTotal = Invoice::whereBetween(
            'invoice_date',
            [$startDate->format('Y-m-d'), $start->format('Y-m-d')]
        )
            ->whereCompany()
            ->whereCustomer($customer->id)
            ->sum('base_total');
        $totalReceipts = Payment::whereBetween(
            'payment_date',
            [$startDate->format('Y-m-d'), $start->format('Y-m-d')]
        )
            ->whereCompany()
            ->whereCustomer($customer->id)
            ->sum('base_amount');
        $totalExpenses = Expense::whereBetween(
            'expense_date',
            [$startDate->format('Y-m-d'), $start->format('Y-m-d')]
        )
            ->whereCompany()
            ->whereUser($customer->id)
            ->sum('base_amount');
        $netProfit = (int) $totalReceipts - (int) $totalExpenses;

        return [
            'months' => $months,
            'invoiceTotals' => $invoiceTotals,
            'expenseTotals' => $expenseTotals,
            'receiptTotals' => $receiptTotals,
            'netProfit' => $netProfit,
            'netProfits' => $netProfits,
            'salesTotal' => $salesTotal,
            'totalReceipts' => $totalReceipts,
            'totalExpenses' => $totalExpenses,
        ];
    }
}
