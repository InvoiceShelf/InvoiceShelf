<?php

namespace App\Services;

use App\Models\Customer;
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
}
