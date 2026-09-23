<?php

namespace App\Platform\Mcp\Tools\Reporting;

use App\Domains\Accounts\Models\CompanySetting;
use App\Domains\Purchases\Models\Expense;
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

#[Name('rank_expense_categories')]
#[Title('Spending by category')]
#[Description('Expense categories ranked by what was spent in them over a period, in the company currency, with the number of expenses.')]
#[IsReadOnly]
#[IsIdempotent]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
class RankExpenseCategoriesTool extends McpTool
{
    use ResolvesPeriod;

    public function __construct(
        private readonly RankingQuery $rankings,
    ) {}

    protected function ability(McpContext $context): ?array
    {
        return ['viewAny', Expense::class];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->periodSchema($schema, 'this_fiscal_year'),
            'limit' => $schema->integer()->min(1)->max(50)->description('How many categories, 10 unless given.'),
        ];
    }

    public function handle(Request $request, McpContext $context): ResponseFactory
    {
        $request->validate(['limit' => ['nullable', 'integer', 'min:1', 'max:50']]);

        $companyId = $context->company->id;
        $period = $this->period($request, $companyId, 'this_fiscal_year');
        $currency = CompanySetting::getSetting('currency', $companyId);

        $rows = $this->rankings->expenseCategories($companyId, $period['from'], $period['to'], (int) ($request->get('limit') ?? 10));

        return Response::structured([
            'period' => $period,
            'categories' => array_map(fn (array $row) => [
                'category_id' => $row['category_id'],
                'name' => $row['name'] ?? 'Uncategorised',
                'spent' => Money::of($row['total'], $currency),
                'expenses' => $row['count'],
            ], $rows),
        ]);
    }
}
