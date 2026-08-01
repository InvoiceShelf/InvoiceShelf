<?php

namespace App\Services\Ai\Tools;

use App\Models\Customer;
use App\Models\Invoice;

class GetCustomerTool extends AiTool
{
    public function name(): string
    {
        return 'get_customer';
    }

    public function description(): string
    {
        return 'Fetch full details for a single customer by ID, including contact info, billing/shipping address, and aggregate totals (invoice count, outstanding balance).';
    }

    public function parameterSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'customer_id' => [
                    'type' => 'integer',
                    'description' => 'The customer ID.',
                ],
            ],
            'required' => ['customer_id'],
        ];
    }

    public function requiredAbility(): ?array
    {
        return ['view-customer', Customer::class];
    }

    public function execute(array $arguments, int $companyId, int $userId): mixed
    {
        $customer = Customer::query()
            ->where('company_id', $companyId)
            ->where('id', (int) ($arguments['customer_id'] ?? 0))
            ->with(['billingAddress', 'shippingAddress'])
            ->first();

        if (! $customer) {
            return ['error' => 'customer_not_found'];
        }

        // Aggregate totals — done with lightweight queries rather than loading every invoice.
        // Issued invoices only: a credit note reverses one, it is not another.
        $invoiceCount = Invoice::query()
            ->where('company_id', $companyId)
            ->where('customer_id', $customer->id)
            ->where('type', Invoice::TYPE_INVOICE)
            ->count();

        $outstanding = (float) Invoice::query()
            ->where('company_id', $companyId)
            ->where('customer_id', $customer->id)
            ->whereIn('paid_status', ['UNPAID', 'PARTIALLY_PAID'])
            ->sum('due_amount');

        return [
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'display_name' => $customer->display_name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'contact_name' => $customer->contact_name,
                'company_name' => $customer->company_name,
                'website' => $customer->website,
                'enable_portal' => (bool) $customer->enable_portal,
                'billing_address' => $customer->billingAddress,
                'shipping_address' => $customer->shippingAddress,
                'totals' => [
                    'invoice_count' => $invoiceCount,
                    'outstanding_amount' => $outstanding,
                ],
            ],
        ];
    }
}
