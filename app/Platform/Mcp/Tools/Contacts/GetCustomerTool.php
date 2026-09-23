<?php

namespace App\Platform\Mcp\Tools\Contacts;

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Contacts\Models\Customer;
use App\Platform\Mcp\McpContext;
use App\Platform\Mcp\Presenters\CustomerPresenter;
use App\Platform\Mcp\Tools\McpTool;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_customer')]
#[Title('Get a customer')]
#[Description('One customer in full: contact details, addresses, custom fields, and what they owe (invoiced, outstanding and overdue, in their own currency).')]
#[IsReadOnly]
#[IsIdempotent]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
class GetCustomerTool extends McpTool
{
    protected function ability(McpContext $context): ?array
    {
        return ['viewAny', Customer::class];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'customer_id' => $schema->integer()->description('The customer id, from search_customers.')->required(),
        ];
    }

    public function handle(Request $request, McpContext $context): Response|ResponseFactory
    {
        $request->validate(['customer_id' => ['required', 'integer']]);

        $customer = Customer::query()
            ->with(['currency', 'billingAddress.country', 'shippingAddress.country'])
            ->where('company_id', $context->company->id)
            ->find($request->get('customer_id'));

        if (! $customer) {
            return Response::error('There is no customer with this id in the company.');
        }

        $this->authorizeRecord($context, 'view', $customer);

        $zone = CompanySetting::getSetting('time_zone', $context->company->id) ?: config('app.timezone');

        return Response::structured(CustomerPresenter::detail($customer, CarbonImmutable::now($zone)->toDateString()));
    }
}
