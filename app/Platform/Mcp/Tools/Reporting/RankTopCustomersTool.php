<?php

namespace App\Platform\Mcp\Tools\Reporting;

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Contacts\Models\Customer;
use App\Platform\Mcp\McpContext;
use App\Platform\Mcp\Presenters\Link;
use App\Platform\Mcp\Presenters\Money;
use App\Platform\Mcp\Queries\RankingQuery;
use App\Platform\Mcp\Tools\Concerns\ResolvesPeriod;
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

#[Name('rank_top_customers')]
#[Title('Top customers')]
#[Description(<<<'TEXT'
    Customers ranked by invoiced_total (issued invoices less credit notes),
    paid_total (payments received), invoice_count or outstanding_balance (what
    they owe now; the period is ignored). Money is in the company currency. Use
    this for "who are our best customers" or "who owes us the most".
    TEXT)]
#[IsReadOnly]
#[IsIdempotent]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
class RankTopCustomersTool extends McpTool
{
    use ResolvesPeriod;

    public function __construct(
        private readonly RankingQuery $rankings,
    ) {}

    protected function ability(McpContext $context): ?array
    {
        return ['viewAny', Customer::class];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'metric' => $schema->string()->enum(RankingQuery::CUSTOMER_METRICS)->description('What to rank by, invoiced_total unless given.'),
            ...$this->periodSchema($schema, 'this_fiscal_year'),
            'limit' => $schema->integer()->min(1)->max(50)->description('How many customers, 10 unless given.'),
        ];
    }

    public function handle(Request $request, McpContext $context): ResponseFactory
    {
        $request->validate(['metric' => ['nullable', 'in:'.implode(',', RankingQuery::CUSTOMER_METRICS)], 'limit' => ['nullable', 'integer', 'min:1', 'max:50']]);

        $companyId = $context->company->id;
        $metric = $request->get('metric') ?? 'invoiced_total';
        $period = $metric === 'outstanding_balance' ? ['from' => null, 'to' => null, 'name' => 'now'] : $this->period($request, $companyId, 'this_fiscal_year');
        $currency = CompanySetting::getSetting('currency', $companyId);

        $rows = $this->rankings->customers($companyId, $metric, $period['from'], $period['to'], (int) ($request->get('limit') ?? 10));

        return Response::structured([
            'metric' => $metric,
            'period' => $period,
            'customers' => array_map(fn (array $row) => [
                'customer_id' => $row['customer_id'],
                'name' => $row['name'],
                'value' => $metric === 'invoice_count' ? $row['value'] : Money::of($row['value'], $currency),
                'app_url' => Link::to("customers/{$row['customer_id']}/view"),
            ], $rows),
        ]);
    }
}
