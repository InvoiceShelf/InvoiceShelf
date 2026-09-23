<?php

namespace App\Platform\Mcp\Tools\Contacts;

use App\Domains\Contacts\Models\Customer;
use App\Platform\Mcp\McpContext;
use App\Platform\Mcp\Presenters\CustomerPresenter;
use App\Platform\Mcp\Tools\Concerns\Paginates;
use App\Platform\Mcp\Tools\McpTool;
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

#[Name('search_customers')]
#[Title('Search customers')]
#[Description('Find customers by name, company, contact or email. Returns their ids, contact details and currency, a page at a time.')]
#[IsReadOnly]
#[IsIdempotent]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
class SearchCustomersTool extends McpTool
{
    use Paginates;

    protected function ability(McpContext $context): ?array
    {
        return ['viewAny', Customer::class];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('Words to look for in the name, company name, contact name or email. Leave out to list every customer.'),
            ...$this->pageSchema($schema),
        ];
    }

    public function handle(Request $request, McpContext $context): ResponseFactory
    {
        $query = Customer::query()->with('currency')->where('company_id', $context->company->id)->orderBy('name')->orderBy('id');

        foreach (preg_split('/\s+/', trim((string) $request->get('query')), -1, PREG_SPLIT_NO_EMPTY) as $term) {
            $needle = '%'.$term.'%';
            $query->where(fn ($where) => $where
                ->where('name', 'LIKE', $needle)
                ->orWhere('company_name', 'LIKE', $needle)
                ->orWhere('contact_name', 'LIKE', $needle)
                ->orWhere('email', 'LIKE', $needle));
        }

        $page = $this->page($query, $request, fn (Customer $customer) => CustomerPresenter::summary($customer));

        return Response::structured(['customers' => $page['rows'], 'page' => $page['page'], 'has_more' => $page['has_more']]);
    }
}
