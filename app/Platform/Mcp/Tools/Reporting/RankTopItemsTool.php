<?php

namespace App\Platform\Mcp\Tools\Reporting;

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Catalog\Models\Item;
use App\Platform\Mcp\McpContext;
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

#[Name('rank_top_items')]
#[Title('Top items')]
#[Description('What sold best over a period, by revenue (in the company currency) or by quantity, from the lines of issued invoices. Lines typed in by hand are grouped by their name.')]
#[IsReadOnly]
#[IsIdempotent]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
class RankTopItemsTool extends McpTool
{
    use ResolvesPeriod;

    public function __construct(
        private readonly RankingQuery $rankings,
    ) {}

    protected function ability(McpContext $context): ?array
    {
        return ['viewAny', Item::class];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'metric' => $schema->string()->enum(RankingQuery::ITEM_METRICS)->description('revenue unless given.'),
            ...$this->periodSchema($schema, 'this_fiscal_year'),
            'limit' => $schema->integer()->min(1)->max(50)->description('How many items, 10 unless given.'),
        ];
    }

    public function handle(Request $request, McpContext $context): ResponseFactory
    {
        $request->validate(['metric' => ['nullable', 'in:'.implode(',', RankingQuery::ITEM_METRICS)], 'limit' => ['nullable', 'integer', 'min:1', 'max:50']]);

        $companyId = $context->company->id;
        $period = $this->period($request, $companyId, 'this_fiscal_year');
        $currency = CompanySetting::getSetting('currency', $companyId);
        $metric = $request->get('metric') ?? 'revenue';

        $rows = $this->rankings->items($companyId, $metric, $period['from'], $period['to'], (int) ($request->get('limit') ?? 10));

        return Response::structured([
            'metric' => $metric,
            'period' => $period,
            'items' => array_map(fn (array $row) => [
                'item_id' => $row['item_id'],
                'name' => $row['name'],
                'revenue' => Money::of($row['revenue'], $currency),
                'quantity' => $row['quantity'],
            ], $rows),
        ]);
    }
}
