<?php

namespace App\Domains\Contacts\Application;

use App\Domains\Contacts\Contracts\CustomerDataPurger;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Metadata\Contracts\CustomFieldValueWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerService
{
    public function __construct(
        private readonly CustomFieldValueWriter $customFieldValueWriter,
        private readonly CustomerDataPurger $customerDataPurger,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>|null  $shippingAddress
     * @param  array<string, mixed>|null  $billingAddress
     */
    public function create(
        array $attributes,
        ?array $shippingAddress = null,
        ?array $billingAddress = null,
        ?iterable $customFields = null,
    ): Customer {
        $customer = DB::transaction(function () use ($attributes, $shippingAddress, $billingAddress, $customFields): Customer {
            $customer = Customer::create($attributes);

            $this->replaceAddresses($customer, $shippingAddress, $billingAddress);

            if ($customFields) {
                $this->customFieldValueWriter->attach($customer, $customFields);
            }

            return $customer;
        });

        return Customer::with('billingAddress', 'shippingAddress', 'fields')->findOrFail($customer->id);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>|null  $shippingAddress
     * @param  array<string, mixed>|null  $billingAddress
     *
     * @throws ValidationException
     */
    public function update(
        Customer $customer,
        array $attributes,
        ?array $shippingAddress = null,
        ?array $billingAddress = null,
        ?iterable $customFields = null,
    ): Customer {
        $hasCurrencyLockedActivity = $customer->quotes()->exists() || $customer->estimates()->exists()
            || $customer->invoices()->exists()
            || $customer->payments()->exists()
            || $customer->recurringInvoices()->exists();

        if (($customer->currency_id !== ($attributes['currency_id'] ?? null)) && $hasCurrencyLockedActivity) {
            throw ValidationException::withMessages([
                'currency_id' => ['you_cannot_edit_currency'],
            ]);
        }

        DB::transaction(function () use ($customer, $attributes, $shippingAddress, $billingAddress, $customFields): void {
            $customer->update($attributes);
            $customer->addresses()->delete();
            $this->replaceAddresses($customer, $shippingAddress, $billingAddress);

            if ($customFields) {
                $this->customFieldValueWriter->update($customer, $customFields);
            }
        });

        return $customer->fresh(['billingAddress', 'shippingAddress', 'fields']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>|null  $shippingAddress
     * @param  array<string, mixed>|null  $billingAddress
     */
    public function updateProfile(
        Customer $customer,
        array $attributes,
        ?array $shippingAddress = null,
        ?array $billingAddress = null,
    ): Customer {
        DB::transaction(function () use ($customer, $attributes, $shippingAddress, $billingAddress): void {
            $customer->update($attributes);

            if ($shippingAddress !== null) {
                $customer->shippingAddress()->delete();
                $customer->addresses()->create($shippingAddress);
            }

            if ($billingAddress !== null) {
                $customer->billingAddress()->delete();
                $customer->addresses()->create($billingAddress);
            }
        });

        return $customer->fresh(['billingAddress', 'shippingAddress', 'fields']);
    }

    /** @param iterable<int, int> $ids */
    public function delete(iterable $ids): bool
    {
        DB::transaction(function () use ($ids): void {
            foreach ($ids as $id) {
                $customer = Customer::find($id);

                if (! $customer) {
                    continue;
                }

                $this->customerDataPurger->purge($customer);
                $customer->addresses()->delete();
                $customer->delete();
            }
        });

        return true;
    }

    /**
     * @param  array<string, mixed>|null  $shippingAddress
     * @param  array<string, mixed>|null  $billingAddress
     */
    private function replaceAddresses(Customer $customer, ?array $shippingAddress, ?array $billingAddress): void
    {
        if ($shippingAddress !== null) {
            $customer->addresses()->create($shippingAddress);
        }

        if ($billingAddress !== null) {
            $customer->addresses()->create($billingAddress);
        }
    }
}
