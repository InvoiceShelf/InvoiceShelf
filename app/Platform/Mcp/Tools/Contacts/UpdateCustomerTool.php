<?php

namespace App\Platform\Mcp\Tools\Contacts;

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Contacts\Application\CustomerService;
use App\Domains\Contacts\Http\Requests\CustomerRequest;
use App\Domains\Contacts\Models\Customer;
use App\Platform\Mcp\McpContext;
use App\Platform\Mcp\Presenters\CustomerPresenter;
use App\Platform\Mcp\Support\DomainRequestValidator;
use App\Platform\Mcp\Tools\Contacts\Concerns\DescribesCustomers;
use App\Platform\Mcp\Tools\McpWriteTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('update_customer')]
#[Title('Change a customer')]
#[Description('Change a customer\'s details. Only what is given changes; within an address, only the given fields.')]
#[IsReadOnly(false)]
#[IsIdempotent(true)]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
class UpdateCustomerTool extends McpWriteTool
{
    use DescribesCustomers;

    public function __construct(
        private readonly DomainRequestValidator $validator,
        private readonly CustomerService $customers,
    ) {}

    protected function ability(McpContext $context): ?array
    {
        return ['edit-customer', Customer::class];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'customer_id' => $schema->integer()->description('The customer, from search_customers.')->required(),
            ...$this->customerSchema($schema, creating: false),
        ];
    }

    protected function write(Request $request, McpContext $context): array
    {
        $customer = Customer::query()
            ->with(['billingAddress', 'shippingAddress'])
            ->where('company_id', $context->company->id)
            ->find($request->get('customer_id'));

        if (! $customer) {
            $this->refuse('There is no customer with this id in the company.', 'customer_id');
        }

        $this->authorizeRecord($context, 'update', $customer);

        $input = $this->customerInput($request->all(), CompanySetting::getSetting('currency', $context->company->id), $customer);
        $validated = $this->validator->validate(CustomerRequest::class, $input, $context, 'PUT', ['customer' => $customer]);

        $customer = $this->customers->update(
            $customer,
            $validated->customerAttributes(),
            $validated->shippingAddress(),
            $validated->billingAddress(),
            $validated->customFields(),
        );

        return CustomerPresenter::summary($customer->fresh('currency'));
    }
}
