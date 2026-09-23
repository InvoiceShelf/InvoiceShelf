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

#[Name('create_customer')]
#[Title('Create a customer')]
#[Description('Add a customer. Only the name is required. Search first (search_customers) so the same customer is not added twice. Portal access is not switched on.')]
#[IsReadOnly(false)]
#[IsIdempotent(false)]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
class CreateCustomerTool extends McpWriteTool
{
    use DescribesCustomers;

    public function __construct(
        private readonly DomainRequestValidator $validator,
        private readonly CustomerService $customers,
    ) {}

    protected function ability(McpContext $context): ?array
    {
        return ['create', Customer::class];
    }

    protected function creates(): bool
    {
        return true;
    }

    public function schema(JsonSchema $schema): array
    {
        return [...$this->customerSchema($schema, creating: true), ...$this->idempotencySchema($schema)];
    }

    protected function write(Request $request, McpContext $context): array
    {
        $input = $this->customerInput($request->all(), CompanySetting::getSetting('currency', $context->company->id));
        $validated = $this->validator->validate(CustomerRequest::class, $input, $context);

        $customer = $this->customers->create(
            $validated->customerAttributes(),
            $validated->shippingAddress(),
            $validated->billingAddress(),
            $validated->customFields(),
        );

        return CustomerPresenter::summary($customer->fresh('currency'));
    }
}
