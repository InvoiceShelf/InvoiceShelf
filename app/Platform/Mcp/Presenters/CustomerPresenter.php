<?php

namespace App\Platform\Mcp\Presenters;

use App\Domains\Contacts\Models\Address;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Sales\Models\Invoice;
use Carbon\CarbonImmutable;

final class CustomerPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function summary(Customer $customer): array
    {
        return [
            'id' => $customer->id,
            'name' => $customer->name,
            'company_name' => $customer->company_name,
            'contact_name' => $customer->contact_name,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'currency' => $customer->currency?->code,
            'app_url' => Link::to("customers/{$customer->id}/view"),
        ];
    }

    /**
     * The customer with addresses, answers and what they owe, in their own
     * currency.
     *
     * @return array<string, mixed>
     */
    public static function detail(Customer $customer, string $today): array
    {
        $issued = Invoice::query()
            ->where('company_id', $customer->company_id)
            ->where('customer_id', $customer->id)
            ->where('type', Invoice::TYPE_INVOICE)
            ->where('status', '!=', Invoice::STATUS_DRAFT);

        $outstanding = (int) $issued->clone()->sum('due_amount');
        $overdue = (int) $issued->clone()->where('due_amount', '>', 0)->where('due_date', '<', $today)->sum('due_amount');

        return self::summary($customer) + [
            'website' => $customer->website,
            'tax_id' => $customer->tax_id,
            'portal_enabled' => (bool) $customer->enable_portal,
            'billing_address' => self::address($customer->billingAddress),
            'shipping_address' => self::address($customer->shippingAddress),
            'balance' => [
                'invoiced' => Money::of($issued->clone()->sum('total'), $customer->currency_id),
                'outstanding' => Money::of($outstanding, $customer->currency_id),
                'overdue' => Money::of($overdue, $customer->currency_id),
                'open_invoices' => $issued->clone()->where('due_amount', '>', 0)->count(),
            ],
            'custom_fields' => CustomFieldAnswers::of($customer),
            'created_at' => CarbonImmutable::parse($customer->getRawOriginal('created_at'))->toDateString(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function address(?Address $address): ?array
    {
        if (! $address) {
            return null;
        }

        return [
            'name' => $address->name,
            'street_1' => $address->address_street_1,
            'street_2' => $address->address_street_2,
            'city' => $address->city,
            'state' => $address->state,
            'zip' => $address->zip,
            'country' => $address->country?->name,
            'phone' => $address->phone,
        ];
    }
}
